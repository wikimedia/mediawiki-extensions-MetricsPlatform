<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\unit;

use Generator;
use MediaWiki\Extension\MetricsPlatform\ExperimentManager;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\ExperimentManager
 */
class ExperimentManagerTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideGetUserExperimentsConfig
	 */
	public function testEnrollUser( array $expected, array $experiments, int $userID, string $message = '' ) {
		$actual = ( new ExperimentManager( $experiments ) )->enrollUser( $userID );

		$this->assertArrayEquals( $expected, $actual, false, false, $message );
	}

	public static function provideGetUserExperimentsConfig(): Generator {
		yield [ [], [],	123 ];

		yield [
			[],
			[
				[
					'slug' => 'dog-breeds',
					'sample' => [
						'unit' => 'session',
						'rate' => 0.0,
					],
					'variants' => [],
				],
			],
			123,
			'Experiment with a sample rate of 0.0 should be filtered.'
		];

		yield [
			[
				'fruit' => 'tropical:pomegranate',
			],
			[
				[
					'slug' => 'fruit',
					'variants' => [
						[
							'name' => 'tropical',
							'values' => [ 'pineapple', 'mango', 'pomegranate' ],
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 1.0,
					],
				],
			],
			123,
			'User is enrolled in an experiment with one variant'
		];

		yield [
			[
				'dinner' => 'unsampled',
			],
			[
				[
					'slug' => 'dinner',
					'variants' => [
						[
							'name' => 'get-takeout',
							'type' => 'boolean',
							'values' => [ 'true', 'false' ]
						]
					],
					'sample' => [
						'rate' => '0.25',
						'unit' => 'session'
					]
				],
			],
			123,
			"User isn't enrolled in an experiment",
		];

		yield [
			[
				'fruit' => 'tropical:pomegranate',
				'dinner' => 'unsampled',
				'dessert' => 'gelato-scoops:1',
			],
			[
				[
					'slug' => 'fruit',
					'variants' => [
						[
							'name' => 'tropical',
							'values' => [ 'pineapple', 'mango', 'pomegranate' ]
						]
					],
					'sample' => [
						'rate' => '1',
						'unit' => 'session'
					]
				],
				[
					'slug' => 'dinner',
					'variants' => [
						[
							'name' => 'get-takeout',
							'type' => 'boolean',
							'values' => [ 'true', 'false' ]
						]
					],
					'sample' => [
						'rate' => '0.25',
						'unit' => 'session'
					]
				],
				[
					'slug' => 'dessert',
					'variants' => [
						[
							'name' => 'gelato-scoops',
							'type' => 'integer',
							'values' => range( 1, 3 )
						]
					],
					'sample' => [
						'rate' => '0.75',
						'unit' => 'session'
					]
				],
			],
			123,
			'User is enrolled in multiple experiments',
		];
	}
}
