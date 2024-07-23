<?php

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use Psr\Log\LoggerInterface;
use WANObjectCache;
use Wikimedia\Stats\StatsFactory;

class InstrumentConfigsFetcher {
	private const VERSION = 1;
	private const HTTP_TIMEOUT = 1;

	public const MPIC_API_ENDPOINT = "/api/v1/instruments";

	/**
	 * Name of the main config key(s) for instrument configuration.
	 *
	 * @var array
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnable',
		'MetricsPlatformInstrumentConfiguratorBaseUrl'
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

	public function getInstrumentConfigs(): ?array {
		if ( !$this->areDependenciesMet() ) {
			$this->logger->warning( 'Dependencies not met for the Metrics Platform Instrument Configs Fetcher.' );
			return [];
		}
		$config = $this->options;
		$cache = $this->WANObjectCache;

		return $cache->getWithSetCallback(
			$cache->makeKey( 'MetricsPlatform', 'InstrumentConfigs', self::VERSION ),
			$cache::TTL_MINUTE,
			function () use ( $config ) {
				$startTime = microtime( true );
				$baseUrl = $config->get( 'MetricsPlatformInstrumentConfiguratorBaseUrl' );
				$url = $baseUrl . self::MPIC_API_ENDPOINT;
				$request = $this->httpRequestFactory->create( $url, [ 'timeout' => self::HTTP_TIMEOUT ] );
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
	}

	public function areDependenciesMet(): bool {
		return $this->options->get( 'MetricsPlatformEnable' );
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
			$params = $error->getParams();
			$labels[] = $key . ': ' . implode( ', ', $params );
		}
		return $labels;
	}
}
