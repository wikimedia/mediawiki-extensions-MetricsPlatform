<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use MediaWiki\Config\Config;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\UserSplitterInstrumentation;

class Coordinator {
	public function __construct(
		private readonly Config $config,
		private readonly ConfigsFetcher $configsFetcher,
		private readonly UserSplitterInstrumentation $userSplitterInstrumentation
	) {
	}

	/**
	 * Get the experimental group assigned to the user.
	 *
	 * Experiment configs are either fetched from the following sources in order:
	 *
	 * 1. The `$wgMetricsPlatformExperiments` config variable; and
	 * 2. The {@link ConfigsFetcher} backing store (using {@link ConfigsFetcher::getExperimentConfigs())
	 *
	 * If the experiment is present in the experiment configs, then the user is enrolled in the experiment and the
	 * experimental group assigned to the user is returned.
	 *
	 * Note well that calling this method has no side effects, i.e. CSS classes aren't added to the output.
	 *
	 * @param string $identifier
	 * @param string $experimentName
	 * @return string|null If the user is enrolled in the experiment, then the name of the experimental group assigned
	 *  to them; otherwise, null
	 */
	public function getAssignmentForUser( string $identifier, string $experimentName ): ?string {
		$activeLoggedInExperiments = $this->config->has( 'MetricsPlatformExperiments' ) ?
			$this->config->get( 'MetricsPlatformExperiments' ) :
			$this->configsFetcher->getExperimentConfigs();

		foreach ( $activeLoggedInExperiments as $experiment ) {
			if ( $experimentName !== $experiment[ 'name' ] ) {
				continue;
			}

			$userHash = $this->userSplitterInstrumentation->getUserHash( $identifier, $experimentName );
			$groups = $experiment[ 'groups' ];

			if ( $this->userSplitterInstrumentation->isSampled(
				$experiment[ 'sample' ][ 'rate' ],
				$experiment[ 'groups' ],
				$userHash
			) ) {
				return $this->userSplitterInstrumentation->getBucket( $groups, $userHash );
			}
		}

		return null;
	}
}
