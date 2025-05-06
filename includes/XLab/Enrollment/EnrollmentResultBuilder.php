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

	public function addExperiment( string $experimentName ): void {
		$this->activeExperiments[] = $experimentName;
	}

	public function addEnrollment(
		string $experimentName,
		string $groupName,
		string $subjectID,
		string $samplingUnit
	): void {
		$this->enrolled[] = $experimentName;
		$this->assigned[ $experimentName ] = $groupName;
		$this->subjectIDs[ $experimentName ] = $subjectID;
		$this->samplingUnits[ $experimentName ] = $samplingUnit;
	}

	public function addOverride( string $experimentName, string $groupName ): void {
		$this->overrides[] = $experimentName;
		$this->assigned[ $experimentName ] = $groupName;
	}

	/**
	 * Returns information about experiments and experiment enrollments that have been added in a
	 * format that can be used by the JS xLab SDK and {@link ExperimentManager}.
	 *
	 * @return array
	 */
	public function build(): array {
		return [
			'active_experiments' => $this->activeExperiments,
			'overrides' => $this->overrides,
			'enrolled' => $this->enrolled,
			'assigned' => $this->assigned,
			'subject_ids' => $this->subjectIDs,
			'sampling_units' => $this->samplingUnits,
		];
	}
}
