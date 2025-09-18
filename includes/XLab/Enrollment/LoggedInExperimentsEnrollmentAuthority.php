<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab\Enrollment;

use MediaWiki\Extension\MetricsPlatform\UserSplitter\UserSplitterInstrumentation;
use MediaWiki\User\CentralId\CentralIdLookup;

class LoggedInExperimentsEnrollmentAuthority implements EnrollmentAuthorityInterface {
	private const SAMPLING_UNIT = 'mw-user';

	private readonly UserSplitterInstrumentation $userSplitterInstrumentation;

	public function __construct(
		private readonly CentralIdLookup $centralIdLookup,
	) {
		$this->userSplitterInstrumentation = new UserSplitterInstrumentation();
	}

	public function enrollUser( EnrollmentRequest $request, EnrollmentResultBuilder $result ): void {
		$user = $request->getGlobalUser();

		if ( !$user->isRegistered() ) {
			return;
		}

		// CentralIdLookup::centralIdFromName does not require to local account to already be attached to the central
		// account, which is often not yet the case, so it should reliably return the correct central id.
		$centralUserID = $this->centralIdLookup->centralIdFromName( $user->getName() );

		if ( !$centralUserID ) {
			return;
		}

		foreach ( $request->getActiveLoggedInExperiments() as $experiment ) {
			$experimentName = $experiment['name'];
			$subjectID = $this->userSplitterInstrumentation->getSubjectId( $centralUserID, $experimentName );

			$result->addExperiment( $experimentName, $subjectID, self::SAMPLING_UNIT );

			$groups = $experiment['groups'];
			$userHash = $this->userSplitterInstrumentation->getUserHash( $centralUserID, $experimentName );

			// Is the user in sample for the experiment?
			$isInSample = $this->userSplitterInstrumentation->isSampled(
				$experiment['sample']['rate'],
				$groups,
				$userHash
			);

			if ( $isInSample ) {
				$result->addAssignment(
					$experimentName,
					$this->userSplitterInstrumentation->getBucket( $groups, $userHash )
				);
			}
		}
	}
}
