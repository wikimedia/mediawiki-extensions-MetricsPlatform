<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use MediaWiki\Extension\MetricsPlatform\UserSplitter\UserSplitterInstrumentation;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\MetricsPlatform\MetricsClient;

class ExperimentManager implements LoggerAwareInterface {
	private UserSplitterInstrumentation $userSplitterInstrumentation;
	private array $experiments;
	private array $experimentEnrollments;
	private MetricsClient $metricsClient;
	private bool $enableOverrides;
	private CentralIdLookup $centralIdLookup;
	private LoggerInterface $logger;

	/**
	 * The name of the querystring parameter or cookie to get experiment enrollment overrides from.
	 */
	public const OVERRIDE_PARAM_NAME = 'mpo';

	/**
	 * Constructs a new Experiment Manager derived from
	 * config or fetched from xLab
	 *
	 * @param array $experimentConfigs
	 * @param bool $enableOverrides
	 * @param MetricsClient $metricsClient
	 * @param CentralIdLookup $centralIdLookup
	 * @param ?LoggerInterface $logger
	 */
	public function __construct(
		array $experimentConfigs,
		bool $enableOverrides,
		MetricsClient $metricsClient,
		CentralIdLookup $centralIdLookup,
		?LoggerInterface $logger = null
	) {
		$this->userSplitterInstrumentation = new UserSplitterInstrumentation();
		$this->initialize( $experimentConfigs );
		$this->enableOverrides = $enableOverrides;
		$this->metricsClient = $metricsClient;
		$this->centralIdLookup = $centralIdLookup;
		$this->logger = $logger ?? new NullLogger();

		$this->experimentEnrollments = [];
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
	 * Initializes experiment enrollments (used to do it with the experiments found in
	 * the `X-Experiment-Enrollments` header)
	 */
	public function setExperimentEnrollments( array $experimentEnrollments ): void {
		$this->experimentEnrollments = [
			'active_experiments' => [],
			'enrolled' => [],
			'assigned' => [],
			'subject_ids' => [],
			'sampling_units' => [],
			'overrides' => [],
		];

		$experimentNames = array_keys( $experimentEnrollments );
		$this->experimentEnrollments['enrolled'] = $experimentNames;
		$this->experimentEnrollments['assigned'] = $experimentEnrollments;
		$this->experimentEnrollments['active_experiments'] = $experimentNames;
		$this->experimentEnrollments['sampling_units'] = array_fill_keys( $experimentNames, 'edge-unique' );
	}

	public function getExperimentEnrollments(): array {
		return $this->experimentEnrollments;
	}

	/**
	 * Get the current user's experiment object.
	 *
	 * @param string $experimentName
	 * @return Experiment
	 */
	public function getExperiment( string $experimentName ): Experiment {
		$isExperimentDefined = array_filter(
			$this->experiments,
			static function ( $experiment ) use ( $experimentName ) {
				return $experiment['name'] === $experimentName;
			}
		);
		$userExperimentConfig = $isExperimentDefined ? $this->getCurrentUserExperiment( $experimentName ) : null;

		if ( $userExperimentConfig === null ) {
			$this->logger->info( 'The ' . $experimentName . ' experiment is not registered. ' .
				'Is the experiment configured and running?' );
		}
		return new Experiment(
			$this->metricsClient,
			$userExperimentConfig
		);
	}

	/**
	 * Get the current user's experiment enrollment details.
	 *
	 * @param string $experimentName
	 * @return array
	 */
	private function getCurrentUserExperiment( string $experimentName ): array {
		if ( in_array( $experimentName, $this->experimentEnrollments['active_experiments'], true ) ||
			in_array( $experimentName, $this->experimentEnrollments['enrolled'], true )
		) {
			return [
				'enrolled' => $experimentName,
				'assigned' => $this->experimentEnrollments['assigned'][ $experimentName ],
				'subject_id' => $this->experimentEnrollments['subject_ids'][ $experimentName ],
				'sampling_unit' => $this->experimentEnrollments['sampling_units'][ $experimentName ],
				'coordinator' => in_array( $experimentName, $this->experimentEnrollments['overrides'] )
					? 'forced'
					: 'xLab'
			];
		}
		return [];
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
	 * * `overrides`
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
	 *     "foo" => "mw-user"
	 *   ],
	 *   "overrides" => [
	 *     "foo",
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
	 * @param User $user
	 * @param WebRequest $request
	 */
	public function enrollUser( User $user, WebRequest $request ): void {
		$result = [
			'active_experiments' => [],
			'enrolled' => [],
			'assigned' => [],
			'subject_ids' => [],
			'sampling_units' => [],
			'overrides' => []
		];

		// Parse 'X-Experiment-Enrollments' header in search of everyone experiments enrollments
		$experimentEnrollmentsHeader = $request->getHeader( 'X-Experiment-Enrollments' );
		$everyoneEnrollments = $this->parseExperimentEnrollmentsHeader( $experimentEnrollmentsHeader );
		$this->setExperimentEnrollments( $everyoneEnrollments );

		// If the user is not logged in, the Experiment Manager won't run the experiment enrollment for
		// logged-in experiments
		if ( !$user->isRegistered() ) {
			return;
		}
		$userId = $this->centralIdLookup->centralIdFromLocalUser( $user );
		if ( $userId === 0 ) {
			return;
		}

		// Parse forceVariant query parameter as an override for bucket assignment.
		$overrides = $this->getEnrollmentOverrides( $request );

		// Of the experiments missing from the user's config, populate the user's experiment names and groups.
		foreach ( $this->experiments as $experiment ) {
			$experimentName = $experiment['name'];

			// Get the user's hash (with experiment name) to assign groups deterministically.
			$userHash = $this->userSplitterInstrumentation->getUserHash( $userId, $experimentName );
			$samplingRatio = $experiment['sampleConfig']['rate'];

			$groups = $experiment['groups'];

			// If the user is forcing a particular group and the group is a valid group for the
			// experiment, then use it.
			if (
				isset( $overrides[$experimentName] ) &&
				array_key_exists( $experimentName, $overrides ) &&
				in_array( $overrides[$experimentName], $groups )
			) {
				$result['enrolled'][] = $experimentName;
				$result['assigned'][$experimentName] = $overrides[$experimentName];
				$result['overrides'][] = $experimentName;

				// subject_ids and sampling_units will be included
				$result['subject_ids'][$experimentName] =
					$this->userSplitterInstrumentation->getSubjectId( $userId, $experimentName );
				$result['sampling_units'][$experimentName] = 'mw-user';
			// If the user is in sample for the experiment, then assign them to a group.
			} elseif ( $this->userSplitterInstrumentation->isSampled( $samplingRatio, $groups, $userHash ) ) {
				$result['enrolled'][] = $experimentName;
				$result['assigned'][$experimentName] =
					$this->userSplitterInstrumentation->getBucket( $groups, $userHash );

				// subject_ids and sampling_units will be included
				$result['subject_ids'][$experimentName] =
					$this->userSplitterInstrumentation->getSubjectId( $userId, $experimentName );
				$result['sampling_units'][$experimentName] = 'mw-user';
			}

			// Anyway, the experiment will be added to the `active_experiments` property
			$result['active_experiments'][] = $experimentName;
		}

		// Merges enrollment results with the ones there might be already (everyone experiments
		// parsed from the `X-Experiment-Enrollments` header)
		$this->experimentEnrollments = array_merge_recursive( $this->experimentEnrollments, $result );
	}

	/**
	 * Parses 'X-Experiment-Enrollments' headers in search of everyone experiments enrollments
	 * If the header is malformed somehow (even partially), an empty array will be returned and an error message logged
	 *
	 * @param string $experimentEnrollmentsHeader The value of the `X-Experiment-Enrollments` header
	 * @return array Experiment enrollment information parsed from the header as a key-value pair
	 * as $experimentName:$assignedGroup.
	 */
	private function parseExperimentEnrollmentsHeader( $experimentEnrollmentsHeader ): array {
		$everyoneEnrollments = [];

		if ( $experimentEnrollmentsHeader ) {
			$experimentEnrollments = explode( ';', $experimentEnrollmentsHeader );
			if ( count( $experimentEnrollments ) > 0 ) {
				foreach ( $experimentEnrollments as $enrollment ) {
					$assigned = explode( '=', $enrollment );
					if ( count( $assigned ) == 2 ) {
						$everyoneEnrollments[$assigned[0]] = $assigned[1];
					} else {
						$everyoneEnrollments = [];
						$this->logger->error(
							'X-Experiment-Enrollments could not be parsed properly. The header is malformed'
						);
						break;
					}
				}
			} else {
				$this->logger->error(
					'X-Experiment-Enrollments could not be parsed properly. The header is malformed'
				);
			}
		}

		return $everyoneEnrollments;
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

	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}
}
