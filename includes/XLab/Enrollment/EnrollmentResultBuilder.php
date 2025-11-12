<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab\Enrollment;

/**
 * Accumulates information about active experiments and experiment enrollments during experiment
 * enrollment sampling.
 */
class EnrollmentResultBuilder {
	private array $activeExperiments = [];
	private array $overrides = [];
	private array $enrolled = [];
	private array $assigned = [];
	private array $subjectIDs = [];
	private array $samplingUnits = [];
	private array $coordinator = [];
	private ?array $enrollments = null;

	public function addExperiment( string $experimentName, string $subjectID, string $samplingUnit ): void {
		$this->activeExperiments[ $experimentName ] = true;
		$this->subjectIDs[ $experimentName ] = $subjectID;
		$this->samplingUnits[ $experimentName ] = $samplingUnit;
	}

	public function addAssignment( string $experimentName, string $groupName, bool $isOverride = false ): void {
		$this->enrolled[ $experimentName ] = true;
		$this->assigned[ $experimentName ] = $groupName;

		if ( $isOverride ) {
			$this->overrides[ $experimentName ] = true;
			$this->coordinator[ $experimentName ] = 'forced';
		} else {
			$this->coordinator[ $experimentName ] = 'xLab';
		}
	}

	/**
	 * Returns information about experiments and experiment enrollments that have been added in a
	 * format that can be used by the JS xLab SDK and {@link ExperimentManager}. Note that this
	 * provides `subject_ids` values.
	 *
	 * @return array
	 */
	public function build(): array {
		if ( $this->enrollments === null ) {
			$this->enrollments = [
				'active_experiments' => array_keys( $this->activeExperiments ),
				'overrides' => array_keys( $this->overrides ),
				'enrolled' => array_keys( $this->enrolled ),
				'assigned' => $this->assigned,
				'subject_ids' => $this->subjectIDs,
				'sampling_units' => $this->samplingUnits,
				'coordinator' => $this->coordinator,
			];
		}
		return $this->enrollments;
	}

	/**
	 * Returns information about experiments and experiment enrollments that have been added in a
	 * format meant for logging, that is, without `subject_ids` data.
	 *
	 * @return array
	 */
	public function getEnrollmentsWithoutSubjectIds(): array {
		$enrollments = $this->build();
		unset( $enrollments['subject_ids'] );
		return array_filter( $enrollments, 'count' );
	}
}
