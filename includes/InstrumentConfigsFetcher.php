<?php

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use MediaWiki\MainConfigNames;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

class InstrumentConfigsFetcher {
	private const VERSION = 1;
	private const HTTP_TIMEOUT = 1;
	private const INSTRUMENT = 1;
	private const EXPERIMENT = 2;
	public const MPIC_API_INSTRUMENTS_ENDPOINT = "/api/v1/instruments";
	public const MPIC_API_EXPERIMENTS_ENDPOINT = "/api/v1/experiments";

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
	private WANObjectCache $WANObjectCache;
	private HttpRequestFactory $httpRequestFactory;
	private LoggerInterface $logger;
	private StatsFactory $statsFactory;
	private StatusFormatter $statusFormatter;

	public function __construct(
		ServiceOptions $options,
		WANObjectCache $WANObjectCache,
		HttpRequestFactory $httpRequestFactory,
		LoggerInterface $logger,
		StatsFactory $statsFactory,
		StatusFormatter $statusFormatter
	) {
		$this->options = $options;
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->WANObjectCache = $WANObjectCache;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = $logger;
		$this->statsFactory = $statsFactory;
		$this->statusFormatter = $statusFormatter;
	}

	public function getInstrumentConfigs(): array {
		return $this->getConfigs( 1 );
	}

	public function getExperimentConfigs(): array {
		return $this->getConfigs( 2 );
	}

	/**
	 * Get the instruments and experiments configuration from the Metrics Platform Configurator API.
	 *
	 * @param int|null $flag Return only the specified kind of variables: self::INSTRUMENT or self::EXPERIMENT.
	 *   For internal use only.
	 * @return array[]
	 */
	private function getConfigs( ?int $flag = null ): array {
		$config = $this->options;
		$cache = $this->WANObjectCache;
		$fname = __METHOD__;

		// Check for which api endpoint should be queried and set corresponding cache key.
		$type = $flag ?? self::INSTRUMENT;
		$endpoint = ( $type > 1 ) ? self::MPIC_API_EXPERIMENTS_ENDPOINT : self::MPIC_API_INSTRUMENTS_ENDPOINT;
		$cacheKey = ( $type === self::EXPERIMENT ) ? 'ExperimentConfigs' : 'InstrumentConfigs';

		$this->logger->debug(
			'Start fetching ' . ( $type === self::EXPERIMENT ? 'experiment' : 'instrument' ) . ' configs'
		);

		$result = $cache->getWithSetCallback(
			$cache->makeKey( 'MetricsPlatform', $cacheKey, self::VERSION ),
			$cache::TTL_MINUTE,
			function () use ( $config, $endpoint, $fname ) {
				$startTime = microtime( true );
				$baseUrl = $config->get( 'MetricsPlatformInstrumentConfiguratorBaseUrl' );
				$url = $baseUrl . $endpoint;
				$request = $this->httpRequestFactory->create( $url, [ 'timeout' => self::HTTP_TIMEOUT ], $fname );
				$status = $request->execute();
				$labels = [];
				if ( $status->isOK() ) {
					$labels[] = 'success';
					$json = $request->getContent();
				} else {
					$errors = $status->getMessages( 'error' );
					$this->logger->warning( $this->statusFormatter->getWikiText( Status::wrap( $status ),
						[ 'error' => $errors, 'content' => $request->getContent() ] ) );

					$labels = $this->getClientErrorLabels( $errors );
					$labels[] = $this->getServerErrorLabel( $status->getValue() );

					$json = null;
				}
				// T368253 Use the Stats library for performance reporting.
				foreach ( $labels as $label ) {
					$this->incrementApiRequestsTotal( $label );
				}
				$this->logApiRequestDuration( $startTime );

				/*
				HttpRequestFactory::create returns a MWHttpRequest object.
				MWHttpRequest::execute returns a Status object which provides status
				codes for more granular Stats reporting. For errors, we return null for
				the json response which represents all the failure modes we care about:
				the network being down (DNS resolution not working), connection timeout,
				request timeout, etc.
				*/
				if ( $json === null ) {
					$this->logger->warning( 'MPIC API is not working.' );
					return [];
				}
				return FormatJson::decode( $json, true );
			},
			[
				'staleTTL' => $cache::TTL_DAY
			]
		);

		$this->logger->debug(
			'End fetching ' . ( $type === self::EXPERIMENT ? 'experiment' : 'instrument' ) . ' configs'
		);

		$result = $this->postProcessResult( $result );
		$nActiveConfigs = count( $result );

		$this->logger->debug( "Fetched { $nActiveConfigs } active config(s)" );

		return $result;
	}

	/**
	 * Post-processes the result of successful request to MPIC by:
	 *
	 * 1. Filtering out disabled instruments/experiments (`status=0`)
	 * 2. Extracting the sample config for the current wiki
	 *
	 * @param array $result An array of configs retrieved from MPIC
	 *  TODO: Add a link to the latest response format specification
	 * @return array
	 */
	protected function postProcessResult( array $result ): array {
		$dbName = $this->options->get( MainConfigNames::DBname );
		$processedResult = [];

		foreach ( $result as $config ) {
			if ( !$config['status'] ) {
				continue;
			}

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
	 * Increment success/failure of MPIC api requests.
	 *
	 * @param string $label
	 */
	private function incrementApiRequestsTotal( string $label ): void {
		$this->statsFactory->getCounter( 'mpic_api_requests_total' )
			->setLabel( 'status', $label )
			->increment();
	}

	/**
	 * Record length of MPIC api requests.
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
