<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Unit;

use MediaWiki\Extension\MetricsPlatform\XLab\EnrollmentCssClassSerializer;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\EnrollmentCssClassSerializer
 */
class EnrollmentCssClassSerializerTest extends MediaWikiUnitTestCase {
	public function testSerialize(): void {
		$enrollments = [
			'active_experiments' => [
				'fruit',
				'dinner',
				'dessert',
				'Not A Slug',
				'$$Experiment Platform$$'
			],
			'enrolled' => [
				'fruit',
				'dinner',
				'Not A Slug',
				'$$Experiment Platform$$',
			],
			'assigned' => [
				'fruit' => 'tropical',
				'dinner' => 'get-takeout',
				'Not A Slug' => 'foo',
				'$$Experiment Platform$$' => 'bar',
			],
			'subject_ids' => [
				'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4',
				'dinner' => '36b2f9b733a701393d8d3e9a9cc2f2bb83de54121d8eeff73936cb1fb4911513',
				'Not A Slug' => '81288746f68f8f404b2c81aadbf9a9a3752c2531899bf68fc323fa7eaed2f267',
				'$$Experiment Platform$$' => 'b5edd3a2b60e77500e403d2f82060c4c51d32815ceec318b7323cfe3d6cf7558',
			],
			'sampling_units' => [
				'fruit' => 'mw-user',
				'dinner' => 'mw-user',
				'Not A Slug' => 'mw-user',
				'$$Experiment Platform$$' => 'mw-user',
			],
			'overrides' => [],
		];

		$expected = [
			'xlab-experiment-fruit',
			'xlab-experiment-fruit-tropical',
			'xlab-experiment-dinner',
			'xlab-experiment-dinner-get-takeout',
			'xlab-experiment-not-a-slug',
			'xlab-experiment-not-a-slug-foo',
			'xlab-experiment-_experiment-platform',
			'xlab-experiment-_experiment-platform-bar',
		];

		$this->assertArrayEquals( $expected, EnrollmentCssClassSerializer::serialize( $enrollments ) );
	}

	public function testSerializeEmptyEnrollments(): void {
		$enrollments = [
			'active_experiments' => [],
			'enrolled' => [],
			'assigned' => [],
			'subject_ids' => [],
			'sampling_units' => [],
			'overrides' => [],
		];

		$this->assertArrayEquals( [], EnrollmentCssClassSerializer::serialize( $enrollments ) );
	}
}
