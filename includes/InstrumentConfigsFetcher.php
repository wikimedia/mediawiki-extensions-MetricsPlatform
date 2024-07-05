<?php

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use Psr\Log\LoggerInterface;
use WANObjectCache;

class InstrumentConfigsFetcher {
	private const VERSION = 1;
	private const HTTP_TIMEOUT = 0.25;

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

	public function __construct(
		ServiceOptions $options,
		WANObjectCache $WANObjectCache,
		HttpRequestFactory $httpRequestFactory,
		LoggerInterface $logger
	) {
		$this->options = $options;
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->WANObjectCache = $WANObjectCache;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = $logger;
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
				$baseUrl = $config->get( 'MetricsPlatformInstrumentConfiguratorBaseUrl' );
				$url = $baseUrl . self::MPIC_API_ENDPOINT;
				$json = $this->httpRequestFactory->get( $url, [ 'timeout' => self::HTTP_TIMEOUT ] );

				/*
				HttpRequestFactory::get is a wrapper for HttpRequestFactory::request
				which returns null on failure and a string on success.
				Null represents all the failure modes we care about: the network being
				down (DNS resolution not working), connection timeout, request timeout.
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
}
