<?php

// NOTE: When adding a method to this class, make sure that the method name is painfully clear
// about what it does to the point of being self-documenting.

namespace MediaWiki\Extension\MetricsPlatform\XLab\Enrollment;

use MediaWiki\Request\WebRequest;
use MediaWiki\User\UserIdentity;

/**
 * Adapts the application state for the `EnrollmentAuthorityInterface` implementations to use.
 */
class EnrollmentRequest {

	/**
	 * The name of the querystring parameter or cookie to get experiment enrollment overrides from.
	 */
	private const OVERRIDES_PARAM_NAME = 'mpo';

	private array $activeLoggedInExperiments;
	private UserIdentity $user;
	private WebRequest $request;

	public function __construct( array $activeLoggedInExperiments, UserIdentity $user, WebRequest $request ) {
		$this->activeLoggedInExperiments = $activeLoggedInExperiments;
		$this->user = $user;
		$this->request = $request;
	}

	public function getRawEveryoneExperimentsEnrollments(): string {
		return $this->request->getHeader( 'X-Experiment-Enrollments' ) ?? '';
	}

	public function getActiveLoggedInExperiments(): array {
		return $this->activeLoggedInExperiments;
	}

	public function getGlobalUser(): UserIdentity {
		return $this->user;
	}

	public function getRawEnrollmentOverridesFromQuery(): string {
		$queryValues = $this->request->getQueryValues();

		return $queryValues[self::OVERRIDES_PARAM_NAME] ?? '';
	}

	public function getRawEnrollmentOverridesFromCookie(): string {
		return $this->request->getCookie( self::OVERRIDES_PARAM_NAME, null, '' );
	}
}
