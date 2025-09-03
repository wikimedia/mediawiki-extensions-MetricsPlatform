<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Unit\XLab\Enrollment;

use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder;
use MediaWikiUnitTestCase;

// Initial test class/cases generated with IntelliJ IDEA AI, then manually fixed and tidied.

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder
 * Of initial interest: the `getEnrollmentsWithoutSubjectIds` method.
 */
class EnrollmentResultBuilderTest extends MediaWikiUnitTestCase {

	/**
	 * Test that `getEnrollmentsWithoutSubjectIds` returns an empty array when no enrollments are added.
	 */
	public function testGetEnrollmentsWithoutSubjectIdsEmpty() {
		$resultBuilder = new EnrollmentResultBuilder();

		$enrollmentsWithoutSubjectIds = $resultBuilder->getEnrollmentsWithoutSubjectIds();

		$this->assertCount(
			0,
			$enrollmentsWithoutSubjectIds,
			'Expected an empty array when no enrollments are added.'
		);
	}

	/**
	 * Test that `getEnrollmentsWithoutSubjectIds` excludes `subject_ids` but retains other enrollment data.
	 */
	public function testGetEnrollmentsWithoutSubjectIdsExcludesSubjectIds() {
		$resultBuilder = new EnrollmentResultBuilder();
		$resultBuilder->addExperiment( 'experiment1', 'subject1', 'samplingUnit1' );
		$resultBuilder->addAssignment( 'experiment1', 'groupA' );

		$enrollmentsWithoutSubjectIds = $resultBuilder->getEnrollmentsWithoutSubjectIds();

		$this->assertArrayNotHasKey(
			'subject_ids', $enrollmentsWithoutSubjectIds,
			'The response should not include `subject_ids`.'
		);
		$this->assertArrayHasKey(
			'active_experiments',
			$enrollmentsWithoutSubjectIds, 'Expected `active_experiments` key in response.'
		);
		$this->assertArrayHasKey(
			'enrolled', $enrollmentsWithoutSubjectIds,
			'Expected `enrolled` key in response.'
		);
		$this->assertArrayHasKey(
			'assigned',
			$enrollmentsWithoutSubjectIds, 'Expected `assigned` key in response.'
		);

		$this->assertSame( [ 'experiment1' ], $enrollmentsWithoutSubjectIds[ 'active_experiments' ] );
		$this->assertSame( [ 'experiment1' ], $enrollmentsWithoutSubjectIds[ 'enrolled' ] );
		$this->assertSame( [ 'experiment1' => 'groupA' ], $enrollmentsWithoutSubjectIds[ 'assigned' ] );
	}

	/**
	 * Test that `getEnrollmentsWithoutSubjectIds` skips empty arrays for built enrollments.
	 */
	public function testGetEnrollmentsWithoutSubjectIdsSkipsEmptyKeys() {
		$resultBuilder = new EnrollmentResultBuilder();
		$resultBuilder->addExperiment( 'experiment1', 'subject1', 'samplingUnit1' );

		$enrollmentsWithoutSubjectIds = $resultBuilder->getEnrollmentsWithoutSubjectIds();

		$this->assertArrayNotHasKey(
			'overrides', $enrollmentsWithoutSubjectIds,
			'Empty `overrides` key should not be included.'
		);
		$this->assertArrayNotHasKey(
			'subject_ids', $enrollmentsWithoutSubjectIds,
			'The response should not include `subject_ids`.'
		);
		$this->assertNotEmpty(
			$enrollmentsWithoutSubjectIds,
			'Expected at least one valid key after filtering empty arrays.'
		);
	}

	/**
	 * Test that `getEnrollmentsWithoutSubjectIds` handles multiple experiments and assignments correctly.
	 */
	public function testGetEnrollmentsWithoutSubjectIdsMultipleExperiments() {
		$resultBuilder = new EnrollmentResultBuilder();
		$resultBuilder->addExperiment( 'experiment1', 'subject1', 'samplingUnit1' );
		$resultBuilder->addExperiment( 'experiment2', 'subject2', 'samplingUnit2' );
		$resultBuilder->addAssignment( 'experiment1', 'groupA' );
		$resultBuilder->addAssignment( 'experiment2', 'groupB', true );

		$enrollmentsWithoutSubjectIds = $resultBuilder->getEnrollmentsWithoutSubjectIds();

		$this->assertArrayNotHasKey(
			'subject_ids', $enrollmentsWithoutSubjectIds, 'The response should not include `subject_ids`.'
		);
		$this->assertSame( [ 'experiment1', 'experiment2' ], $enrollmentsWithoutSubjectIds[ 'active_experiments' ] );
		$this->assertSame( [ 'experiment1', 'experiment2' ], $enrollmentsWithoutSubjectIds[ 'enrolled' ] );
		$this->assertSame(
			[ 'experiment1' => 'groupA', 'experiment2' => 'groupB' ],
			$enrollmentsWithoutSubjectIds['assigned']
		);
		$this->assertSame( [ 'experiment2' ], $enrollmentsWithoutSubjectIds[ 'overrides' ] );
	}
}
