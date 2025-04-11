<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use MediaWiki\Config\Config;
use MediaWiki\Extension\EventLogging\EventLogging;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;

/**
 * Provides a stable API to construct an instance of {@link ExperimentManager}.
 */
class ExperimentManagerFactory {
	private InstrumentConfigsFetcher $configsFetcher;
	private Config $config;
	private LoggerInterface $logger;

	public function __construct(
		Config $config,
		InstrumentConfigsFetcher $configsFetcher,
		LoggerInterface $logger
	) {
		Assert::parameter(
			$config->has( 'MetricsPlatformEnableExperimentOverrides' ),
			'$config',
			'Required config "MetricsPlatformEnableExperimentOverrides" missing.'
		);
		Assert::parameter(
			$config->has( 'MetricsPlatformEnableExperimentConfigsFetching' ),
			'$config',
			'Required config "MetricsPlatformEnableExperimentConfigsFetching" missing.'
		);

		$this->config = $config;
		$this->configsFetcher = $configsFetcher;
		$this->logger = $logger;
	}

	/**
	 * Creates a new instance of {@link ExperimentManager} using configs fetched from either local config (highest
	 * priority) or from XLab (lowest priority).
	 *
	 * @return ExperimentManager
	 */
	public function newInstance(): ExperimentManager {
		$experimentConfigs = [];

		if ( $this->config->has( 'MetricsPlatformExperiments' ) ) {
			$experimentConfigs = $this->config->get( 'MetricsPlatformExperiments' );
		} elseif ( $this->config->get( 'MetricsPlatformEnableExperimentConfigsFetching' ) ) {
			$experimentConfigs = $this->configsFetcher->getExperimentConfigs();
		}

		return new ExperimentManager(
			$experimentConfigs,
			$this->config->get( 'MetricsPlatformEnableExperimentOverrides' ),
			EventLogging::getMetricsPlatformClient(),
			$this->logger
		);
	}
}
