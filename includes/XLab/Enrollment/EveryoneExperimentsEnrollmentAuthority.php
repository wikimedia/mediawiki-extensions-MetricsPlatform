<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab\Enrollment;

use Psr\Log\LoggerInterface;

/**
 * Allows xLab to handle everyone experiment enrollments from Varnish.
 *
 * In WMF production, Varnish is the enrollment authority for everyone experiments and MediaWiki
 * is the enrollment authority for logged-in experiments. When a user is enrolled into an
 * experiment by Varnish, group assignment is communicated to MediaWiki via the
 * `X-Experiment-Enrollments` header. `EveryoneEnrollmentAuthorityInterface` parses that header
 * and adds experiment enrollments to the enrollment result.
 *
 * Varnish *does not* send subject IDs in the `X-Experiment-Enrollments` so `"awaiting"` is used as
 * a placeholder instead.
 */
class EveryoneExperimentsEnrollmentAuthority implements EnrollmentAuthorityInterface {
	private const SAMPLING_UNIT = 'edge-unique';

	/**
	 * The subject ID to use in the absence of a subject ID from Varnish.
	 */
	private const SUBJECT_ID = 'awaiting';

	private LoggerInterface $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	public function enrollUser( EnrollmentRequest $request, EnrollmentResultBuilder $result ): void {
		$header = $request->getRawEveryoneExperimentsEnrollments();

		if ( !$header ) {
			return;
		}

		$rawAssignments = explode( ';', rtrim( $header, ';' ) );
		$assignments = [];

		foreach ( $rawAssignments as $rawAssignment ) {
			$assignment = explode( '=', $rawAssignment );

			if ( count( $assignment ) === 2 ) {
				$assignments[ $assignment[0] ] = $assignment[1];
			} else {
				$this->logger->error(
					'The X-Experiment-Enrollments header could not be parsed properly. The header is malformed.'
				);

				return;
			}
		}

		foreach ( $assignments as $experimentName => $groupName ) {
			$result->addExperiment( $experimentName );
			$result->addEnrollment(
				$experimentName,
				$groupName,
				self::SUBJECT_ID,
				self::SAMPLING_UNIT
			);
		}
	}
}
