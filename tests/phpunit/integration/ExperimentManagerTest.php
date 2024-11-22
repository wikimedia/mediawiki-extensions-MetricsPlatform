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
					'features' => [],
				],
			],
			'Experiment with a sample rate of 0.0 should be filtered.'
		];

		yield [
			[
				'enrolled' => [
					'fruit'
				],
				'assigned' => [
					'tropical' => 'pomegranate'
				],
				'features' => [
					'fruit' => [
						'tropical'
					]
				]
			],
			[
				[
					'slug' => 'fruit',
					'features' => [
						'tropical' => [
							'control' => 'pineapple',
							'values' => [ 'pineapple', 'mango', 'pomegranate' ],
						]
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
				'enrolled' => [],
				'assigned' => [
					'get-takeout' => 'unsampled'
				],
				'features' => [
					'dinner' => [
						'get-takeout'
					]
				]
			],
			[
				[
					'slug' => 'dinner',
					'features' => [
						'get-takeout' => [
							'control' => 'true',
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
				'enrolled' => [
					'fruit',
					'dessert'
				],
				'assigned' => [
					'tropical' => 'pomegranate',
					'berries' => 'strawberry',
					'get-takeout' => 'unsampled',
					'gelato-scoops' => '1'
				],
				'features' => [
					'fruit' => [
						'tropical',
						'berries'
					],
					'dinner' => [
						'get-takeout'
					],
					'dessert' => [
						'gelato-scoops'
					]
				]
			],
			static::getMultipleExperimentConfigs(),
			'User is enrolled in multiple experiments with one experiment having multiple features to be tested.',
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
					'features' => [],
				],
			],
			'seasons:winter:true',
			'Experiment with a sample rate of 0.0 should be filtered.'
		];

		yield [
			[
				'enrolled' => [
					'fruit'
				],
				'assigned' => [
					'tropical' => 'mango'
				],
				'features' => [
					'fruit' => [
						'tropical'
					]
				]
			],
			[
				[
					'slug' => 'fruit',
					'features' => [
						'tropical' => [
							'control' => 'pineapple',
							'values' => [ 'pineapple', 'mango', 'pomegranate' ]
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
				'enrolled' => [
					'fruit',
					'dessert'
				],
				'assigned' => [
					'tropical' => 'pomegranate',
					'berries' => 'raspberry',
					'get-takeout' => 'unsampled',
					'gelato-scoops' => '3'
				],
				'features' => [
					'fruit' => [
						'tropical',
						'berries'
					],
					'dinner' => [
						'get-takeout'
					],
					'dessert' => [
						'gelato-scoops'
					]
				]
			],
			$multipleExperimentConfigs,
			'dessert:gelato-scoops:3;fruit:berries:raspberry',
			'User is enrolled in multiple experiments with one overridden variant',
		];

		yield [
			[
				'enrolled' => [
					'fruit',
					'dessert'
				],
				'assigned' => [
					'tropical' => 'pomegranate',
					'berries' => 'strawberry',
					'get-takeout' => 'unsampled',
					'gelato-scoops' => '3'
				],
				'features' => [
					'fruit' => [
						'tropical',
						'berries'
					],
					'dinner' => [
						'get-takeout'
					],
					'dessert' => [
						'gelato-scoops'
					]
				]
			],
			$multipleExperimentConfigs,
			'fruit:tropical:pomegranate;fruit:berries:strawberry;dessert:gelato-scoops:3',
			'User is enrolled in multiple experiments with multiple overridden variants',
		];

		yield [
			[
				'enrolled' => [
					'fruit',
					'dessert'
				],
				'assigned' => [
					'berries' => 'strawberry',
					'get-takeout' => 'unsampled',
					'gelato-scoops' => '1'
				],
				'features' => [
					'fruit' => [
						'tropical',
						'berries'
					],
					'dinner' => [
						'get-takeout'
					],
					'dessert' => [
						'gelato-scoops'
					]
				]
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
				'features' => [
					'tropical' => [
						'control' => 'pineapple',
						'values' => [ 'pineapple', 'mango', 'pomegranate' ]
					],
					'berries' => [
						'control' => 'blueberry',
						'values' => [ 'blueberry', 'raspberry', 'strawberry' ]
					],
				],
				'sample' => [
					'rate' => '1',
					'unit' => 'session'
				]
			],
			[
				'slug' => 'dinner',
				'features' => [
					'get-takeout' => [
						'control' => 'true',
						'values' => [ 'true', 'false' ]
					],
				],
				'sample' => [
					'rate' => '0.25',
					'unit' => 'session'
				]
			],
			[
				'slug' => 'dessert',
				'features' => [
					'gelato-scoops' => [
						'control' => 1,
						'values' => range( 1, 3 )
					],
				],
				'sample' => [
					'rate' => '0.75',
					'unit' => 'session'
				]
			],
		];
	}
}
