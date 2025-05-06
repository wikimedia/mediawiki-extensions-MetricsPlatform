<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use DomainException;
use Psr\Log\LoggerInterface;
use Wikimedia\MetricsPlatform\MetricsClient;

class ExperimentManager {
	private LoggerInterface $logger;
	private MetricsClient $metricsPlatformClient;
	private array $enrollmentResult;

	public function __construct( LoggerInterface $logger, MetricsClient $metricsPlatformClient ) {
		$this->logger = $logger;
		$this->metricsPlatformClient = $metricsPlatformClient;
		$this->enrollmentResult = [];
	}

	/**
	 * This method SHOULD NOT be called by code outside of the MetricsPlatform extension (or the xLab codebase).
	 *
	 * @internal
	 *
	 * @param array $enrollmentResult
	 * @throws DomainException If this instance of `ExperimentManager` has already been initialized with one or more
	 *  experiment enrollments
	 */
	public function initialize( array $enrollmentResult ): void {
		if ( $this->enrollmentResult ) {
			throw new DomainException( 'ExperimentManager has already been initialized.' );
		}

		$this->enrollmentResult = $enrollmentResult;
	}

	/**
	 * Get the current user's experiment object.
	 *
	 * @param string $experimentName
	 * @return Experiment
	 */
	public function getExperiment( string $experimentName ): Experiment {
		$isExperimentDefined = in_array( $experimentName, $this->enrollmentResult['active_experiments'], true );
		$experimentConfig = [];

		if ( !$isExperimentDefined ) {
			$this->logger->info( 'The ' . $experimentName . ' experiment is not registered. ' .
				'Is the experiment configured and running?' );
		} else {
			$experimentConfig = $this->getCurrentUserExperiment( $experimentName );
		}

		return new Experiment( $this->metricsPlatformClient, $experimentConfig );
	}

	/**
	 * Get the current user's experiment enrollment details.
	 *
	 * @param string $experimentName
	 * @return array
	 */
	private function getCurrentUserExperiment( string $experimentName ): array {
		return in_array( $experimentName, $this->enrollmentResult['enrolled'], true ) ?
			[
				'enrolled' => $experimentName,
				'assigned' => $this->enrollmentResult['assigned'][ $experimentName ],
				'subject_id' => $this->enrollmentResult['subject_ids'][ $experimentName ],
				'sampling_unit' => $this->enrollmentResult['sampling_units'][ $experimentName ],
				'coordinator' => in_array( $experimentName, $this->enrollmentResult['overrides'] )
					? 'forced'
					: 'xLab'
			] : [];
	}
}
