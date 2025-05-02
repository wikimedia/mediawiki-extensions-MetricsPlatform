<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use MediaWiki\Parser\Sanitizer;

class EnrollmentCssClassSerializer {

	/**
	 * Serializes the enrollments of a user in all active experiments to a list of CSS classes that can be added to an
	 * HTML element.
	 *
	 * @param array $enrollments See {@link ExperimentManager::enrollUser()} and
	 *  {@link ExperimentManager::getExperimentEnrollments()}
	 * @return string[]
	 */
	public static function serialize( array $enrollments ): array {
		$result = [];

		foreach ( $enrollments['assigned'] as $experimentName => $groupName ) {
			$class = 'xlab-experiment-' . self::serializeName( $experimentName );

			$result[] = $class;
			$result[] = $class . '-' . self::serializeName( $groupName );
		}

		return $result;
	}

	/**
	 * Serializes the name so that it can be used as part of a CSS class.
	 *
	 * This implementation follows the one at [data-engineering/mpic/src/composables/utility.js#L5][0], which is also
	 * owned and maintained by the Experiment Platform team.
	 *
	 * [0]: https://gitlab.wikimedia.org/repos/data-engineering/mpic/-/blob/24466ffd9590c35e129ee8c431f9ad4145498fee/frontend/src/composables/utility.js#L5
	 *
	 * @param string $name
	 * @return string
	 */
	private static function serializeName( string $name ): string {
		$result = strtolower( $name );
		$result = preg_replace( '/\s+/', '-', $result );
		$result = Sanitizer::escapeClass( $result );

		return $result;
	}
}
