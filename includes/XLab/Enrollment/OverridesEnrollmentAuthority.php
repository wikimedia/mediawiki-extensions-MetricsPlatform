<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab\Enrollment;

use MediaWiki\Config\ServiceOptions;

/**
 * Allows xLab to handle overrides for testing purposes.
 *
 * Prior to launch, a feature should be tested at every stage of the feature development cycle. A
 * developer needs to test the feature before she requests review and a QTE needs to test the
 * feature before they sign it off. We could ask them to sign up for users until they are enrolled
 * into an experiment and assigned the desired group or we could provide them a mechanism to
 * override experiment enrollments.
 *
 * `OverridesEnrollmentAuthority` parses a cookie and/or a query parameter and adds overrides to
 * the enrollment result. If `$wgMetricsPlatformEnableExperimentOverrides` is falsy, then this
 * functionality is disabled.
 *
 * See https://doc.wikimedia.org/MetricsPlatform/master/js/mw.xLab.html#.overrideExperimentGroup
 * for more detail about the client-side part to overriding experiment enrollments.
 */
class OverridesEnrollmentAuthority implements EnrollmentAuthorityInterface {
	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableExperimentOverrides',
	];

	private bool $isEnabled;

	public function __construct( ServiceOptions $options ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->isEnabled = $options->get( 'MetricsPlatformEnableExperimentOverrides' );
	}

	public function enrollUser( EnrollmentRequest $request, EnrollmentResultBuilder $result ): void {
		if ( !$this->isEnabled ) {
			return;
		}

		$assignments = array_merge(
			$this->processRawEnrollmentOverrides(
				$request->getRawEnrollmentOverridesFromCookie()
			),
			$this->processRawEnrollmentOverrides(
				$request->getRawEnrollmentOverridesFromQuery()
			)
		);

		foreach ( $assignments as $experimentName => $groupName ) {
			$result->addOverride( $experimentName, $groupName );
		}
	}

	/**
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
	 * this function will return a map of experiment name to group name.
	 *
	 * @param string $rawEnrollmentOverrides
	 * @return array
	 */
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
