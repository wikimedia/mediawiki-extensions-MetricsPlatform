<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Integration;

use Generator;
use MediaWiki\Extension\MetricsPlatform\ExperimentManager;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\ExperimentManager
 */
class ExperimentManagerTest extends MediaWikiIntegrationTestCase {

	private UserIdentity $user;
	private WebRequest $request;

	public function setUp(): void {
		parent::setUp();

		$this->user = new UserIdentityValue( 123, self::class );
		$this->request = new FauxRequest();
	}

	/**
	 * @dataProvider provideGetUserExperimentsConfig
	 */
	public function testEnrollUser(
		array $expected,
		array $experiments,
		string $message = ''
	) {
		$actual = ( new ExperimentManager( $experiments, false ) )->enrollUser( $this->user, $this->request );

		$this->assertArrayEquals( $expected, $actual, false, false, $message );
	}

	public static function provideGetUserExperimentsConfig(): Generator {
		yield [ [], [] ];

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
			"User isn't enrolled in an experiment",
		];

		yield [
			[
				'fruit' => 'tropical:pomegranate',
				'dinner' => 'unsampled',
				'dessert' => 'gelato-scoops:1',
			],
			static::getMultipleExperimentConfigs(),
			'User is enrolled in multiple experiments',
		];
	}

	public static function provideGetUserExperimentsConfigWithOverrides(): Generator {
		$multipleExperimentConfigs = static::getMultipleExperimentConfigs();

		yield [ [], [], 'seasons:winter:true' ];

		yield [
			[],
			[
				[
					'slug' => 'seasons',
					'sample' => [
						'unit' => 'session',
						'rate' => 0.0,
					],
					'variants' => [],
				],
			],
			'seasons:winter:true',
			'Experiment with a sample rate of 0.0 should be filtered.'
		];

		yield [
			[
				'fruit' => 'tropical:mango',
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
			'fruit:tropical:mango',
			'User is enrolled in an experiment with one overridden variant'
		];

		yield [
			[
				'fruit' => 'tropical:pomegranate',
				'dinner' => 'unsampled',
				'dessert' => 'gelato-scoops:3',
			],
			$multipleExperimentConfigs,
			'dessert:gelato-scoops:3',
			'User is enrolled in multiple experiments with one overridden variant',
		];

		yield [
			[
				'fruit' => 'tropical:pineapple',
				'dinner' => 'unsampled',
				'dessert' => 'gelato-scoops:1',
			],
			$multipleExperimentConfigs,
			'fruit:tropical:pineapple',
			'User is enrolled in multiple experiments with another overridden variant',
		];

		yield [
			[
				'fruit' => 'tropical:pomegranate',
				'dinner' => 'unsampled',
				'dessert' => 'gelato-scoops:1',
			],
			$multipleExperimentConfigs,
			'fruit:tropical:cheese',
			'Overridden variant must have a valid value',
		];
	}

	/**
	 * @dataProvider provideGetUserExperimentsConfigWithOverrides
	 */
	public function testEnrollUserWithOverrides(
		array $expected,
		array $experiments,
		string $overrides,
		string $message = ''
	) {
		$this->request->setCookie( ExperimentManager::OVERRIDE_PARAM_NAME, $overrides );

		$actual = ( new ExperimentManager( $experiments, true ) )->enrollUser( $this->user, $this->request );

		$this->assertArrayEquals( $expected, $actual, false, false, $message );
	}

	private static function getMultipleExperimentConfigs(): array {
		return [
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
		];
	}
}
