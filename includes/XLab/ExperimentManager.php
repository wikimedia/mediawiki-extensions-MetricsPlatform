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
		$enrolledExperiments = $this->enrollmentResult['enrolled'] ?? [];

		// The user is not enrolled in the experiment (also because the experiment doesn't exist)
		if ( !in_array( $experimentName, $enrolledExperiments, true ) )	{
			$this->logger->info( 'The ' . $experimentName . ' experiment is not registered. ' .
				'Is the experiment configured and running?' );
			return new UnenrolledExperiment();
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
