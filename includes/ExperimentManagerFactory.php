<?php

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\Config\Config;

/**
 * Provides a stable API to construct an instance of {@link ExperimentManager}.
 */
class ExperimentManagerFactory {

	private InstrumentConfigsFetcher $configsFetcher;
	private Config $config;

	public function __construct( InstrumentConfigsFetcher $configsFetcher, Config $config ) {
		$this->configsFetcher = $configsFetcher;
		$this->config = $config;
	}

	/**
	 * Creates a new instance of {@link ExperimentManager} using configs fetched from either local config (highest
	 * priority) or from MPIC (lowest priority).
	 *
	 * @return ExperimentManager
	 */
	public function newInstance(): ExperimentManager {
		if ( $this->config->has( 'MetricsPlatformExperiments' ) ) {
			return new ExperimentManager( $this->config->get( 'MetricsPlatformExperiments' ) );
		}

		return new ExperimentManager( $this->configsFetcher->getExperimentConfigs() );
	}
}
