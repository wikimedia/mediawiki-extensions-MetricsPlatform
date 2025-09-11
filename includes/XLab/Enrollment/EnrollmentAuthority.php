<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab\Enrollment;

/**
 * The main enrollment authority to be used to perform experiment enrollment sampling in WMF
 * production.
 *
 * This `EnrollmentAuthorityInterface` implementation is simply composed of the other implementations. Calling
 * {@link EnrollmentAuthority::enrollUser()} simply calls the following methods **in order**:
 *
 * 1. {@link EveryoneExperimentsEnrollmentAuthority::enrollUser()}
 * 2. {@link LoggedInExperimentsEnrollmentAuthority::enrollUser()}
 * 3. {@link OverridesEnrollmentAuthority::enrollUser()}
 */
class EnrollmentAuthority implements EnrollmentAuthorityInterface {
	public function __construct(
		private readonly EveryoneExperimentsEnrollmentAuthority $everyoneExperimentsEnrollmentAuthority,
		private readonly LoggedInExperimentsEnrollmentAuthority $loggedInExperimentsEnrollmentAuthority,
		private readonly OverridesEnrollmentAuthority $overridesEnrollmentAuthority,
	) {
	}

	public function enrollUser( EnrollmentRequest $request, EnrollmentResultBuilder $result ): void {
		$this->everyoneExperimentsEnrollmentAuthority->enrollUser( $request, $result );
		$this->loggedInExperimentsEnrollmentAuthority->enrollUser( $request, $result );
		$this->overridesEnrollmentAuthority->enrollUser( $request, $result );
	}
}
