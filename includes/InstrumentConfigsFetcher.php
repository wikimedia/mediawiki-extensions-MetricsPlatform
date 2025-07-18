<?php

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use MediaWiki\MainConfigNames;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use Psr\Log\LoggerInterface;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Stats\StatsFactory;

class InstrumentConfigsFetcher {
	private const VERSION = 1;
	private const HTTP_TIMEOUT = 1;
	private const INSTRUMENT = 1;
	private const EXPERIMENT = 2;
	private const USER_AGENT = 'InstrumentConfigsFetcher/0.0.1 (#experiment-platform)';
	public const XLAB_API_INSTRUMENTS_ENDPOINT = "/api/v1/instruments";
	public const XLAB_API_EXPERIMENTS_ENDPOINT = "/api/v1/experiments?format=config&authority=mediawiki";

	/**
	 * Name of the main config key(s) for instrument configuration.
	 *
	 * @var array
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformInstrumentConfiguratorBaseUrl',
		MainConfigNames::DBname,
	];
	private ServiceOptions $options;
	private BagOStuff $objectCache;
	private HttpRequestFactory $httpRequestFactory;
	private LoggerInterface $logger;
	private StatsFactory $statsFactory;
	private StatusFormatter $statusFormatter;

	public function __construct(
		ServiceOptions $options,
		BagOStuff $objectCache,
		HttpRequestFactory $httpRequestFactory,
		LoggerInterface $logger,
		StatsFactory $statsFactory,
		StatusFormatter $statusFormatter
	) {
		$this->options = $options;
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->objectCache = $objectCache;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = $logger;
		$this->statsFactory = $statsFactory;
		$this->statusFormatter = $statusFormatter;
	}

	/**
	 * Gets instrument configs from the backing store. If there are no instrument configs in the backing store, then
	 * they are not fetched from xLab.
	 */
	public function getInstrumentConfigs(): array {
		return $this->getConfigs( self::INSTRUMENT );
	}

	/**
	 * Gets experiment configs from the backing store. If there are no experiment configs in the backing store, then
	 * they are not fetched from xLab.
	 */
	public function getExperimentConfigs(): array {
		return $this->getConfigs( self::EXPERIMENT );
	}

	private function getConfigs( int $type ): array {
		$configs = $this->objectCache->get( $this->makeCacheKey( $type ), BagOStuff::READ_VERIFIED ) ?: [];

		return $this->processConfigs( $configs );
	}

	/**
	 * Fetch instrument configs from xLab and update the backing store if they have changed.
	 *
	 * @internal
	 */
	public function updateInstrumentConfigs(): void {
		$this->updateConfigs( self::INSTRUMENT );
	}

	/**
	 * Fetch experiment configs from xLab and update the backing store if they have changed.
	 *
	 * @internal
	 */
	public function updateExperimentConfigs(): void {
		$this->updateConfigs( self::EXPERIMENT );
	}

	private function updateConfigs( int $type ): void {
		$isExperiment = $type === self::EXPERIMENT;
		$configsTypeFragment = ( $isExperiment ? 'experiment' : 'instrument' ) . ' configs';

		$this->logger->debug( 'Start updating ' . $configsTypeFragment );

		$newValueStatus = $this->fetchConfigs( $type );

		// Was there an error fetching the configs?
		if ( !$newValueStatus->isGood() ) {
			// NOTE: fetchConfigs() handles logging.
			return;
		}

		// NOTE: Since the status result of the call to fetchConfigs() was good, we know that we can re-encode the value
		// of the status result without error.
		$newValue = $newValueStatus->getValue();
		$newValueJson = FormatJson::encode( $newValue );

		$key = $this->makeCacheKey( $type );
		$oldValue = $this->objectCache->get( $key );
		$oldValueJson = FormatJson::encode( $oldValue );

		if ( $newValueJson !== $oldValueJson ) {
			$this->logger->info( 'Change detected. Updating ' . $configsTypeFragment );

			$this->objectCache->delete( $key );
			$this->objectCache->set( $key, $newValue, ExpirationAwareness::TTL_WEEK );
		}

		$this->logger->debug( 'End updating ' . $configsTypeFragment );
	}

	private function makeCacheKey( int $type ): string {
		return $this->objectCache->makeGlobalKey(
			'MetricsPlatform',
			$type === self::EXPERIMENT ? 'experiment' : 'instrument',
			self::VERSION
		);
	}

	private function fetchConfigs( int $type ): Status {
		$isExperiment = $type === self::EXPERIMENT;
		$endpoint = $isExperiment ? self::XLAB_API_EXPERIMENTS_ENDPOINT : self::XLAB_API_INSTRUMENTS_ENDPOINT;
		$url = $this->options->get( 'MetricsPlatformInstrumentConfiguratorBaseUrl' ) . $endpoint;

		$startTime = microtime( true );
		$request = $this->httpRequestFactory->create(
			$url,
			[
				'timeout' => self::HTTP_TIMEOUT,
				'logger' => $this->logger
			],
			__METHOD__
		);

		// T398957: Identify the agent fetching the configs in the similar way to the configs fetchers running
		// on the cache-proxy nodes.
		//
		// See https://gerrit.wikimedia.org/r/plugins/gitiles/operations/puppet/+/refs/heads/production/modules/profile/files/cache/wmfuniq_experiment_fetcher.py#52
		$request->setHeader( 'User-Agent', self::USER_AGENT );
		$request->setHeader( 'X-Experiment-Config-Poller', wfHostname() );

		$status = $request->execute();

		$this->logApiRequestDuration( $startTime );

		$responseBody = $request->getContent();
		$responseStatusCode = $request->getStatus();

		if ( !$responseStatusCode < 200 && $responseStatusCode >= 300 ) {
			$status->fatal( 'metricsplatform-xlab-non-successful-response' );
		}

		if ( !$responseBody ) {
			$status->fatal( 'metricsplatform-xlab-api-empty-response-body' );
		} else {
			$status->merge( FormatJson::parse( $responseBody, FormatJson::FORCE_ASSOC ), true );
		}

		$labels = [ 'success' ];

		if ( !$status->isGood() ) {
			$errors = $status->getMessages( 'error' );

			$this->logger->warning( ...$this->statusFormatter->getPsr3MessageAndContext(
				$status,
				[
					'errors' => $errors,
					'content' => $responseBody
				]
			) );

			$labels = $this->getClientErrorLabels( $errors );
			$labels[] = $this->getServerErrorLabel( $responseStatusCode );
		}

		// T368253 Use the Stats library for performance reporting.
		foreach ( $labels as $label ) {
			$this->incrementApiRequestsTotal( $label );
		}

		return $status;
	}

	/**
	 * Post-processes the result of successful request to xLab by:
	 *
	 * 1. Filtering out disabled instruments/experiments (`status=0`)
	 * 2. Extracting the sample config for the current wiki
	 *
	 * @param array $result An array of configs retrieved from xLab
	 *  TODO: Add a link to the latest response format specification
	 * @return array
	 */
	protected function processConfigs( array $result ): array {
		$dbName = $this->options->get( MainConfigNames::DBname );
		$processedResult = [];

		foreach ( $result as $config ) {
			$config['sample'] = $this->getSampleConfig( $config, $dbName );

			$processedResult[] = $config;
		}

		return $processedResult;
	}

	/**
	 * @param array $config
	 * @param string $dbName
	 * @return array
	 */
	private function getSampleConfig( array $config, string $dbName ) {
		$sampleConfig = [
			'rate' => 0.0,
			'unit' => 'session',
		];

		if ( array_key_exists( 'sample_rate', $config ) ) {
			$sampleRates = $config['sample_rate'];
			$sampleConfig['rate'] = $sampleRates['default'];
			unset( $sampleRates['default'] );

			foreach ( $sampleRates as $rate => $wikis ) {
				if ( in_array( $dbName, $wikis ) ) {
					$sampleConfig['rate'] = $rate;

					break;
				}
			}
		}

		if ( array_key_exists( 'sample_unit', $config ) ) {
			$sampleConfig['unit'] = $config['sample_unit'];
		}

		return $sampleConfig;
	}

	/**
	 * Increment success/failure of xLab API requests.
	 *
	 * @param string $label
	 */
	private function incrementApiRequestsTotal( string $label ): void {
		$this->statsFactory->getCounter( 'mpic_api_requests_total' )
			->setLabel( 'status', $label )
			->increment();
	}

	/**
	 * Record duration of xLab API requests.
	 *
	 * @param float $startTime
	 */
	private function logApiRequestDuration( float $startTime ): void {
		$this->statsFactory->getTiming( 'mpic_api_request_duration_seconds' )
			->observe( ( microtime( true ) - $startTime ) * 1000 );
	}

	/**
	 * Get error label based on http status code.
	 *
	 * @param int $statusCode
	 * @return string
	 */
	private function getServerErrorLabel( int $statusCode ): string {
		switch ( $statusCode ) {
			case 400:
				$label = 'bad-request';
				break;
			case 408:
				$label = 'server-timeout';
				break;
			case 500:
				$label = 'internal-server-error';
				break;
			default:
				$label = 'failure';
		}
		return $label;
	}

	/**
	 * Get client error labels based on message keys and params.
	 *
	 * Error labels are crafted by the message key that is passed into the
	 * fatal method of the status property of the request object when connection
	 * exceptions are thrown by GuzzleHttpRequest::execute(). Examples include:
	 * - 'http-timed-out'
	 * - 'http-curl-error'
	 * - 'http-request-error'
	 * - 'http-internal-error'
	 *
	 * @param array $errors
	 * @return array
	 */
	private function getClientErrorLabels( array $errors ): array {
		$labels = [];
		// Loop through error messages to aggregate counters of different types.
		foreach ( $errors as $error ) {
			$key = $error->getKey();

			$paramValues = array_map( static function ( $param ) {
				return $param->getValue();
			}, $error->getParams() );
			$paramString = implode( ', ', $paramValues );

			$labels[] = $key . ': ' . $paramString;
		}
		return $labels;
	}
}
