<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab\Enrollment;

/**
 * Represents a process that performs experiment enrollment sampling, the act of enrolling a user
 * into one or more active experiments.
 */
interface EnrollmentAuthorityInterface {

	/**
	 * Try to enroll the user into all active experiments.
	 *
	 * An experiment is considered to be active if:
	 *
	 * 1. It is marked as active in xLab (i.e. `experiment.status=1`)
	 * 2. It has a sample rate of > 0.0
	 * 3. It has a start date before the current date and an end date after the current date (i.e.
	 *    `experiment.start_date <= current_date < end_date`)
	 *
	 * A user may or may not be enrolled into an experiment. If the user is enrolled in the experiment, then they are
	 * assigned a group. If the user is not enrolled in an experiment, then they are unsampled, and no other action is
	 * taken.
	 *
	 * @param EnrollmentRequest $request
	 * @param EnrollmentResultBuilder $result
	 */
	public function enrollUser( EnrollmentRequest $request, EnrollmentResultBuilder $result ): void;
}
