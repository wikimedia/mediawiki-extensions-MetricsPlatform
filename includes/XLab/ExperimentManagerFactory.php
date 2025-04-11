<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use MediaWiki\Config\Config;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use Wikimedia\Assert\Assert;

/**
 * Provides a stable API to construct an instance of {@link ExperimentManager}.
 */
class ExperimentManagerFactory {
	private InstrumentConfigsFetcher $configsFetcher;
	private Config $config;

	public function __construct( Config $config, InstrumentConfigsFetcher $configsFetcher ) {
		Assert::parameter(
			$config->has( 'MetricsPlatformEnableExperimentOverrides' ),
			'$config',
			'Required config "MetricsPlatformEnableExperimentOverrides" missing.'
		);

		$this->config = $config;
		$this->configsFetcher = $configsFetcher;
	}

	/**
	 * Creates a new instance of {@link ExperimentManager} using configs fetched from either local config (highest
	 * priority) or from XLab (lowest priority).
	 *
	 * @return ExperimentManager
	 */
	public function newInstance(): ExperimentManager {
		$experimentConfigs = $this->config->has( 'MetricsPlatformExperiments' ) ?
			$this->config->get( 'MetricsPlatformExperiments' ) :
			$this->configsFetcher->getExperimentConfigs();

		return new ExperimentManager(
			$experimentConfigs,
			$this->config->get( 'MetricsPlatformEnableExperimentOverrides' )
		);
	}
}
