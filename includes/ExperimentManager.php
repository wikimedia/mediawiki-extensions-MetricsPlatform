<?php

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\Extension\MetricsPlatform\UserSplitter\UserSplitterInstrumentation;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\UserIdentity;

class ExperimentManager {
	private UserSplitterInstrumentation $userSplitterInstrumentation;
	private array $experiments;
	private bool $enableOverrides;

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
	 * 1. It is marked as active in xLab (i.e. `experiment.status=1`); and
	 * 2. It has a sample rate of > 0.0
	 *
	 * A user may or may not be enrolled into an experiment. If the user is enrolled in the experiment, then they are
	 * assigned a group.
	 *
	 * The result of enrolling the user into all active experiments is an array with the following keys:
	 *
	 * * `active_experiments`
	 * * `enrolled`
	 * * `assigned`
	 * * `subject_ids`
	 * * `sampling_units`
	 *
	 * If the user is not enrolled in an experiment, then they are unsampled, and no other action is taken.
	 *
	 * If the user is enrolled in an experiment, then:
	 *
	 * 1. The experiment name will be added to the `enrolled` array
	 * 2. A key-value pair composed of the experiment name and the assigned group will be added the `assigned` map
	 * 3. A key-value pair composed of the experiment name and the subject ID (created as
	 *    `hash('sha256', user->getUserId(), experimentName)`) will be added to the `subject_ids` map
	 * 4. A key-value pair composed of the experiment name and the sampling_unit (`mw-user`) will be added to the
	 *    `sampling_units` map
	 *
	 * For example:
	 *
	 * ```
	 * [
	 *   "active_experiments" => [
	 *     "foo",
	 *   ],
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
	 * @param UserIdentity $user
	 * @param WebRequest $request
	 * @return array
	 * @phan-return array{active_experiments:string[],enrolled:string[],assigned:array{string,string},subject_ids:array{string,string},sampling_units:array{string,'mw-user'}}
	 */
	public function enrollUser( UserIdentity $user, WebRequest $request ): array {
		$result = [
			'active_experiments' => [],
			'enrolled' => [],
			'assigned' => [],
			'subject_ids' => [],
			'sampling_units' => [],
		];

		// Parse forceVariant query parameter as an override for bucket assignment.
		$overrides = $this->getEnrollmentOverrides( $request );

		// Of the experiments missing from the user's config, populate the user's experiment names and groups.
		foreach ( $this->experiments as $experiment ) {
			$experimentName = $experiment['name'];

			// Get the user's hash (with experiment name) to assign groups deterministically.
			$userHash = $this->userSplitterInstrumentation->getUserHash( $user->getId(), $experimentName );
			$samplingRatio = $experiment['sampleConfig']['rate'];

			$groups = $experiment['groups'];

			// If the user is forcing a particular group and the group is a valid group for the
			// experiment, then use it.
			if (
				isset( $overrides[$experimentName] ) &&
				array_key_exists( $experimentName, $overrides )
			) {
				if ( in_array( $overrides[$experimentName], $groups ) ) {
					$result['enrolled'][] = $experimentName;
					$result['assigned'][$experimentName] = $overrides[$experimentName];

					// subject_ids and sampling_units will be included
					$result['subject_ids'][$experimentName] = hash( 'sha256', $user->getId() . $experimentName );
					$result['sampling_units'][$experimentName] = 'mw-user';
				}

			// If the user is in sample for the experiment, then assign them to a group.
			} elseif ( $this->userSplitterInstrumentation->isSampled( $samplingRatio, $groups, $userHash ) ) {
				$result['enrolled'][] = $experimentName;
				$result['assigned'][$experimentName] =
					$this->userSplitterInstrumentation->getBucket( $groups, $userHash );

				// subject_ids and sampling_units will be included
				$result['subject_ids'][$experimentName] = hash( 'sha256', $user->getId() . $experimentName );
				$result['sampling_units'][$experimentName] = 'mw-user';
			}

			// Anyway the experiment will be added to the `active_experiments` property
			$result['active_experiments'][] = $experimentName;
		}

		return $result;
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
