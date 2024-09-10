<?php

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\Extension\MetricsPlatform\UserSplitter\UserSplitterInstrumentation;

class ExperimentManager {
	private UserSplitterInstrumentation $userSplitterInstrumentation;
	private array $experiments;

	public const EXCLUDED_BUCKET_NAME = 'unsampled';

	public function __construct( array $experimentConfigs ) {
		$this->userSplitterInstrumentation = new UserSplitterInstrumentation();
		$this->initialize( $experimentConfigs );
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
			$buckets = [];

			foreach ( $experimentConfig['variants'] as $variant ) {
				foreach ( $variant['values'] as $value ) {
					$buckets[] = $variant['name'] . ':' . $this->castAsString( $value );
				}
			}

			$experiments[] = [
				'name' => $experimentConfig['slug'],
				'buckets' => $buckets,
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
	 * Currently, a bucket is equivalent to a variant/value pair. For example: an experiment, "foo", with one variant,
	 * "bar", which can have four values, [ 1, 2, 3, 4 ], corresponds to four buckets:
	 *
	 * 1. `bar:1`
	 * 2. `bar:2`
	 * 3. `bar:3`
	 * 4. `bar:4`
	 *
	 * The result of enrolling the user into all active experiments is a map of experiment name to bucket (or
	 * variant/value pair). In the example above, the result will look something like:
	 *
	 * ```
	 * [
	 *   "foo" => "bar:3",
	 * ]
	 * ```
	 *
	 * @param int $userId
	 * @return array<string, string>
	 */
	public function enrollUser( int $userId ): array {
		// TODO (phuedx, 2024/09.19): What about accepting an instance of UserIdentity or a string?

		$experimentsByName = [];

		// Of the experiments missing from the user's config, populate the user's experiment names and buckets.
		foreach ( $this->experiments as $experiment ) {
			// Get the user's hash (with experiment name) to assign buckets deterministically.
			$userHash = $this->userSplitterInstrumentation->getUserHash( $userId, $experiment['name'] );
			$samplingRatio = $experiment['sampleConfig']['rate'];
			$buckets = $experiment['buckets'];

			// If the user is in sample, return the bucket name, or else that the user is unsampled.
			$experimentsByName[$experiment['name']] = $this->userSplitterInstrumentation
				->isSampled( $samplingRatio, $buckets, $userHash )
				? $this->userSplitterInstrumentation->getBucket( $buckets, $userHash )
				: self::EXCLUDED_BUCKET_NAME;
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
}
