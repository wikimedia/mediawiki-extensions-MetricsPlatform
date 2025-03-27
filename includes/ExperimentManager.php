<?php

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\Extension\MetricsPlatform\UserSplitter\UserSplitterInstrumentation;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\UserIdentity;

class ExperimentManager {
	private UserSplitterInstrumentation $userSplitterInstrumentation;
	private array $experiments;
	private bool $enableOverrides;

	public const EXCLUDED_BUCKET_NAME = 'unsampled';

	/**
	 * The name of the querystring parameter or cookie to get experiment enrollment overrides from.
	 */
	public const OVERRIDE_PARAM_NAME = 'mpo';

	public function __construct( array $experimentConfigs, bool $enableOverrides ) {
		$this->userSplitterInstrumentation = new UserSplitterInstrumentation();
		$this->initialize( $experimentConfigs );
		$this->enableOverrides = $enableOverrides;
	}

	private function initialize( array $experimentConfigs ): void {
		$experiments = [];

		foreach ( $experimentConfigs as $experimentConfig ) {
			$sampleConfig =	$experimentConfig['sample'];
			if ( $sampleConfig['rate'] === 0.0 ) {
				continue;
			}

			$experiments[] = [
				'name' => $experimentConfig['slug'],
				'groups' => $experimentConfig['groups'],
				'sampleConfig' => $sampleConfig
			];

		}

		$this->experiments = $experiments;
	}

	/**
	 * Try to enroll the user into all active experiments.
	 *
	 * An experiment is considered to be active if:
	 *
	 * 1. It is marked as active in MPIC (i.e. `experiment.status=1`); and
	 * 2. It has a sample rate of > 0.0
	 *
	 * A user may or may not be enrolled into an experiment. If the user is enrolled in the experiment, then they are
	 * assigned a bucket; otherwise they are marked an "unsampled."
	 *
	 * Currently, a bucket is equivalent to a group name. For example: an experiment "foo" that has a 'control' and a
	 * treatment group called `a_treatment_group`, corresponds to two buckets:
	 *
	 * ```
	 * `foo` => [
	 *   `groups` => [
	 *     `control`,
	 * 	   `a_treatment_group`
	 *   ],
	 * ],
	 * ```
	 *
	 * The result of enrolling the user into all active experiments is an array with two keys: `enrolled` and `assigned`
	 *
	 * If the user is not enrolled in an experiment, then they are unsampled, and no other action is taken.
	 *
	 * If the user is enrolled in an experiment, then:
	 *
	 * 1. The experiment name will be added to the `enrolled` array
	 * 2. A key-value pair composed of the experiment name and the assigned group will be added the `assigned` map
	 * 3. The subject_id (created as hash('sha256', user->getUserId(), experimentName))
	 *    will be added to the `subject_ids` map
	 * 4. A key-value pair composed of the experiment name and the sampling_unit (`mw-user`)
	 *    will be added to the `sampling_units` map
	 *
	 * In the example above, the result will look something like:
	 *
	 * ```
	 * [
	 *   "enrolled" => [
	 *     "foo",
	 *   ],
	 *   "assigned" => [
	 *     "foo" => "control",
	 *   ],
	 * 	 "subject_ids" => [
	 *     "foo" => "2b1138ed5e31c7f7093c211714c4b751f8b9ca863e3dc72ac53a28bef6c08e0d"
	 *   ],
	 *   "sampling_units" => [
	 *     "foo" =>  "mw-user"
	 *   ]
	 * ]
	 * ```
	 *
	 * Note that bucket assignments can be forced by applying a query parameter if the feature is enabled
	 * (see `$wgMetricsPlatformEnableExperimentOverrides`) using the following format:
	 *
	 * ```
	 * <experiment name>:<group name>
	 * ```
	 *
	 * e.g.
	 *
	 * * `donate-cta-ab-test:cta-color`
	 * * `dark-mode-ab-test:dark-mode`
	 * * `sticky-header-ab-test:control`
	 *
	 * This override feature is only available for a single experiment at this time.
	 *
	 * @param UserIdentity $user
	 * @param WebRequest $request
	 * @return array<string, string>
	 */
	public function enrollUser( UserIdentity $user, WebRequest $request ): array {
		$experimentsByName = [];
		$enrollment = [];
		$enrollment['enrolled'] = [];

		// Parse forceVariant query parameter as an override for bucket assignment.
		$overrides = $this->getEnrollmentOverrides( $request );

		// Of the experiments missing from the user's config, populate the user's experiment names and buckets.
		foreach ( $this->experiments as $experiment ) {
			$experimentName = $experiment['name'];

			// Get the user's hash (with experiment name) to assign buckets deterministically.
			$userHash = $this->userSplitterInstrumentation->getUserHash( $user->getId(), $experimentName );
			$samplingRatio = $experiment['sampleConfig']['rate'];

			$buckets = $experiment['groups'];
			// If the user is forcing a particular bucket, use the override.
			if (
				isset( $overrides[$experimentName] ) &&
				array_key_exists( $experimentName, $overrides )
			) {
				// If the overridden value is a legitimate bucket name, enroll the user
				// and make the bucket assignment. If the overridden value does not match
				// available bucket names, the user is not enrolled in the experiment.
				if ( in_array( $overrides[$experimentName], $buckets ) ) {
					$enrollment['enrolled'][] = $experimentName;
					$enrollment['assigned'][$experimentName] = $overrides[$experimentName];
				}

				// If the user is in sample, return the bucket name.
			} elseif ( $this->userSplitterInstrumentation->isSampled( $samplingRatio, $buckets, $userHash ) ) {
				$enrollment['enrolled'][] = $experimentName;
				$assignedBucket = $this->userSplitterInstrumentation->getBucket( $buckets, $userHash );
				$enrollment['assigned'][$experimentName] = $this->castAsString( $assignedBucket );

				// Otherwise, the user is unsampled.
			} else {
				$enrollment['assigned'][$experimentName] = self::EXCLUDED_BUCKET_NAME;
			}

			// Anyway subject_ids and sampling_units will be included
			$enrollment['subject_ids'][$experimentName] = hash( 'sha256', $user->getId() . $experimentName );
			$enrollment['sampling_units'][$experimentName] = 'mw-user';

			// Dedupe the enrolled array which will have duplicate experiment names
			// if an experiment has multiple features.
			$enrollment['enrolled'] = array_unique( $enrollment['enrolled'] );
			$experimentsByName = $enrollment;
		}
		return $experimentsByName;
	}

	/**
	 * Convert a boolean or integer to a string for concatenation
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function castAsString( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) ) {
			return strval( $value );
		}
		if ( is_string( $value ) ) {
			return $value;
		}
		return '';
	}

	/**
	 * Get any experiment enrollment overrides from the request.
	 *
	 * Given raw experiment enrollment overrides in the form:
	 *
	 * ```
	 * $en1:$gn1;$en2:$gn2;...
	 * ```
	 *
	 * where:
	 *
	 * * `$en` is the experiment name
	 * * `$gn` is the group name
	 *
	 * this function will return a map of experiment name to group name pair in the same form as
	 * {@link ExperimentManager::enrollUser()}.
	 *
	 * This method will return experiment enrollment overrides from the `mpo` querystring parameter and from the
	 * `mpo` cookie. The querystring parameter takes precedence over the cookie.
	 *
	 * @param WebRequest $request
	 * @return array
	 */
	private function getEnrollmentOverrides( WebRequest $request ): array {
		if ( !$this->enableOverrides ) {
			return [];
		}

		$queryValues = $request->getQueryValues();
		$queryEnrollmentOverrides = $this->processRawEnrollmentOverrides(
			$queryValues[self::OVERRIDE_PARAM_NAME] ?? ''
		);

		$cookieEnrollmentOverrides = $this->processRawEnrollmentOverrides(
			$request->getCookie( self::OVERRIDE_PARAM_NAME, null, '' )
		);

		return array_merge( $cookieEnrollmentOverrides, $queryEnrollmentOverrides );
	}

	private function processRawEnrollmentOverrides( string $rawEnrollmentOverrides ): array {
		$result = [];

		if ( !$rawEnrollmentOverrides ) {
			return $result;
		}

		// TODO: Should we limit the number of overrides that we accept?
		$parts = explode( ';', $rawEnrollmentOverrides );

		foreach ( $parts as $override ) {
			[ $experimentName, $groupName ] = explode( ':', $override, 2 );
			$result[$experimentName] = $groupName;
		}

		return $result;
	}
}
