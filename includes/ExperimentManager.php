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

			// Loop through an experiment's feature variants to aggregate each experiment's available bucket names
			// prefixed with its feature variant name(s). Because feature variant values are generic (i.e. a string,
			// an integer, or a boolean), the feature variant name is prefixed to all possible feature variant values to
			// create meaningful, specific bucket names for all experiments.
			$features = [];

			foreach ( $experimentConfig['features'] as $featureName => $feature ) {
				if ( array_key_exists( 'control', $feature ) ) {
					$features[$featureName]['control'] = $feature['control'];
				}
				$features[$featureName]['values'] = $feature['values'];
			}

			$experiments[] = [
				'name' => $experimentConfig['slug'],
				'features' => $features,
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
	 * Currently, a bucket is equivalent to a variant/value pair. For example: an experiment, "foo", with one feature
	 * variant "bar", which can have four values, `[ 1, 2, 3, 4 ]`, corresponds to four buckets:
	 *
	 * ```
	 * `foo` => [
	 *   `bar` => [
	 *     `values` => [
	 *       1,
	 *       2,
	 *       3,
	 *       4,
	 *     ],
	 *   ],
	 * ],
	 * ```
	 *
	 * The result of enrolling the user into all active experiments is an array with three keys: `enrolled`, `assigned`,
	 * and `features`.
	 *
	 * If the user is not enrolled in an experiment, then they are unsampled, and no other action is taken.
	 *
	 * If the user is enrolled in an experiment, then:
	 *
	 * 1. The experiment name will be added to the `enrolled` array
	 * 2. The map of feature name to assigned feature value is merged into the `assigned` map; and
	 * 3. The map of experiment name to feature names is merged into the `features` map
	 *
	 * In the example above, the result will look something like:
	 *
	 * ```
	 * [
	 *   "enrolled" => [
	 *     "foo",
	 *   ],
	 *   "assigned" => [
	 *     "bar => 3",
	 *   ],
	 *   "features" => [
	 *     "foo" => [
	 *       "bar",
	 *     ],
	 *   ],
	 * ]
	 * ```
	 *
	 * Note that bucket assignments can be forced by applying a query parameter if the feature is enabled
	 * (see `$wgMetricsPlatformEnableExperimentOverrides`) using the following format:
	 *
	 * ```
	 * <experiment name>:<feature variant name>:<feature variant value>
	 * ```
	 *
	 * e.g.
	 *
	 * * `donate-cta-ab-test:cta-color:red`
	 * * `dark-mode-ab-test:dark-mode:true`
	 * * `sticky-header-ab-test:show-sticky-header:false`
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

			foreach ( $experiment['features'] as $featureName => $feature ) {
				$buckets = $this->castBuckets( $experiment['features'][$featureName]['values'] );

				// Assign the correlation between experiment names and feature variant names in all cases.
				// Note that an experiment can have multiple features to be tested.
				$enrollment['features'][$experimentName][] = $featureName;

				// If the user is forcing a particular bucket, use the override.
				if (
					isset( $overrides[$experimentName] ) &&
					array_key_exists( $featureName, $overrides[$experimentName] )
				) {
					// If the overridden value is a legitimate bucket name, enroll the user
					// and make the bucket assignment. If the overridden value does not match
					// available bucket names, the user is not enrolled in the experiment.
					if ( in_array( $overrides[$experimentName][$featureName], $buckets ) ) {
						$enrollment['enrolled'][] = $experimentName;
						$enrollment['assigned'][$featureName] = $overrides[$experimentName][$featureName];
					}

					// If the user is in sample, return the bucket name.
				} elseif ( $this->userSplitterInstrumentation->isSampled( $samplingRatio, $buckets, $userHash ) ) {
					$enrollment['enrolled'][] = $experimentName;
					$assignedBucket = $this->userSplitterInstrumentation->getBucket( $buckets, $userHash );
					$enrollment['assigned'][$featureName] = $this->castAsString( $assignedBucket );

					// Otherwise, the user is unsampled.
				} else {
					$enrollment['assigned'][$featureName] = self::EXCLUDED_BUCKET_NAME;
				}
			}
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
	 * Convert a boolean or integer values in a bucket to a string for comparison
	 *
	 * @param array $values
	 * @return array
	 */
	private function castBuckets( array $values ): array {
		$castValues = [];
		foreach ( $values as $value ) {
			$castValues[] = $this->castAsString( $value );
		}
		return $castValues;
	}

	/**
	 * Get any experiment enrollment overrides from the request.
	 *
	 * Given raw experiment enrollment overrides in the form:
	 *
	 * ```
	 * $en1:$fvn1:$fvv1;$en2:$fvn2:$fvv2;...
	 * ```
	 *
	 * where:
	 *
	 * * `$en` is the experiment name
	 * * `$fvn` is the feature variant name
	 * * `$fvv` is the feature variant value
	 *
	 * this function will return a map of experiment name to feature variant name/value pair in the same form as
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
			// $key is the experiment name
			[ $key, $value ] = explode( ':', $override, 2 );
			$feature = explode( ':', $value, 2 );
			$featureName = $feature[0];
			$featureValue = $feature[1];
			$result[$key][$featureName] = $featureValue;
		}

		return $result;
	}
}
