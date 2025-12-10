<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use Psr\Log\LoggerInterface;
use Wikimedia\MetricsPlatform\MetricsClient;
use Wikimedia\Stats\StatsFactory;

class ExperimentManager implements ExperimentManagerInterface {
	private array $enrollmentResult;
	private StatsFactory $statsFactory;

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly MetricsClient $metricsPlatformClient,
		StatsFactory $statsFactory
	) {
		$this->enrollmentResult = [];
		$this->statsFactory = $statsFactory;
	}

	/**
	 * This method SHOULD NOT be called by code outside the MetricsPlatform extension (or the xLab codebase). As an
	 * interim solution GrowthExperiments uses it on account creation until T405074 is resolved.
	 *
	 * Don't use this unless you've spoken with Experiment Platform team.
	 *
	 * @param array $enrollmentResult
	 */
	public function initialize( array $enrollmentResult ): void {
		$this->enrollmentResult = $enrollmentResult;
	}

	/**
	 * Get the current user's experiment object.
	 *
	 * @param string $experimentName
	 * @return Experiment
	 */
	public function getExperiment( string $experimentName ): Experiment {
		$activeExperiments = $this->enrollmentResult['active_experiments'] ?? [];
		$enrolledExperiments = $this->enrollmentResult['enrolled'] ?? [];

		if (
			in_array( $experimentName, $activeExperiments, true ) &&
			$this->enrollmentResult['sampling_units'][ $experimentName ] === 'mw-user' &&
			!in_array( $experimentName, $enrolledExperiments, true )
		) {
			// For logged-in experiments we know whether the experiment is active, but the current user
			// is not enrolled in it
			$this->logger->info( 'The current user is not enrolled in ' .
				'the ' . $experimentName . ' experiment' );
			return new UnenrolledExperiment();
		} else {
			// For now, regarding logged-out experiments, there is no way to distinguish between
			// an experiment that is not active, doesn't exist or the current user is not enrolled in
			if ( !in_array( $experimentName, $enrolledExperiments, true ) ) {
				$this->logger->info(
					'{experiment} is not active or the current user is not enrolled in. ' .
					'Is the experiment configured and running?',
					[
						'experiment' => $experimentName
					]
				);
				return new UnenrolledExperiment();
			}
		}

		$experimentConfig = $this->getExperimentConfig( $experimentName );

		// The experiment enrolment has been overridden
		if ( $experimentConfig['coordinator'] === 'forced' ) {
			return new OverriddenExperiment( $this->logger, $experimentConfig );
		}

		return new Experiment( $this->metricsPlatformClient, $this->statsFactory, $experimentConfig );
	}

	/**
	 * Get the current user's experiment enrollment details.
	 *
	 * @param string $experimentName
	 * @return array
	 */
	private function getExperimentConfig( string $experimentName ): array {
		return [
			'enrolled' => $experimentName,
			'assigned' => $this->enrollmentResult['assigned'][ $experimentName ],
			'subject_id' => $this->enrollmentResult['subject_ids'][ $experimentName ],
			'sampling_unit' => $this->enrollmentResult['sampling_units'][ $experimentName ],
			'coordinator' => $this->enrollmentResult['coordinator'][ $experimentName ]
		];
	}
}
