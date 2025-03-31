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

	private const NULL_RESULT = [
		'active_experiments' => [],
		'enrolled' => [],
		'assigned' => [],
		'subject_ids' => [],
		'sampling_units' => [],
	];

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
		yield [ self::NULL_RESULT, [] ];

		yield [
			self::NULL_RESULT,
			[
				[
					'slug' => 'dog-breeds',
					'sample' => [
						'rate' => 0.0
					],
					'groups' => [],
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
					'fruit' => 'control'
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4'
				],
				'sampling_units' => [
					'fruit' => 'mw-user'
				],
				'active_experiments' => [
					'fruit'
				]
			],
			[
				[
					'slug' => 'fruit',
					'groups' => [
						'mango',
						'control'
					],
					'sample' => [
						'rate' => 1.0
					],
				],
			],
			'User is enrolled in an experiment'
		];

		yield [
			[
				'active_experiments' => [
					'dinner'
				],
				'enrolled' => [],
				'assigned' => [],
				'subject_ids' => [],
				'sampling_units' => [],
			],
			[
				[
					'slug' => 'dinner',
					'groups' => [
						'control',
						'soap'
					],
					'sample' => [
						'rate' => '0.25'
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
					'fruit' => 'control',
					'dessert' => 'control'
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4',
					'dessert' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397'
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user'
				],
				'active_experiments' => [
					'fruit',
					'dinner',
					'dessert'
				]
			],
			static::getMultipleExperimentConfigs(),
			'User is enrolled in multiple experiments',
		];
	}

	public static function provideGetUserExperimentsConfigWithOverrides(): Generator {
		$multipleExperimentConfigs = static::getMultipleExperimentConfigs();

		yield [ self::NULL_RESULT, [], 'seasons:winter' ];

		yield [
			self::NULL_RESULT,
			[
				[
					'slug' => 'seasons',
					'sample' => [
						'rate' => 0.0,
					],
					'groups' => [],
				],
			],
			'seasons:control',
			'Experiment with a sample rate of 0.0 should be filtered.'
		];

		yield [
			[
				'enrolled' => [
					'fruit'
				],
				'assigned' => [
					'fruit' => 'control'
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4'
				],
				'sampling_units' => [
					'fruit' => 'mw-user'
				],
				'active_experiments' => [
					'fruit'
				]
			],
			[
				[
					'slug' => 'fruit',
					'groups' => [
						'control',
						'tropical',
					],
					'sample' => [
						'rate' => 1.0,
					],
				],
			],
			'fruit:control',
			'User is enrolled in an experiment with one overridden variant'
		];

		yield [
			[
				'enrolled' => [
					'fruit',
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'gelato-scoops'
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4',
					'dessert' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397'
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user'
				],
				'active_experiments' => [
					'fruit',
					'dinner',
					'dessert'
				]
			],
			$multipleExperimentConfigs,
			'dessert:gelato-scoops;fruit:tropical',
			'User is enrolled in multiple experiments with one overridden group',
		];

		yield [
			[
				'enrolled' => [
					'fruit',
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'gelato-scoops'
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4',
					'dessert' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397'
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user'
				],
				'active_experiments' => [
					'fruit',
					'dinner',
					'dessert'
				]
			],
			$multipleExperimentConfigs,
			'fruit:control;fruit:tropical;dessert:gelato-scoops',
			'User is enrolled in multiple experiments with multiple overridden groups',
		];

		yield [
			[
				'enrolled' => [
					'fruit',
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'control'
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4',
					'dessert' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397'
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user'
				],
				'active_experiments' => [
					'fruit',
					'dinner',
					'dessert'
				]
			],
			$multipleExperimentConfigs,
			'fruit:tropical',
			'Overridden group must have a valid value',
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
				'groups' => [
					'tropical',
					'control',
				],
				'sample' => [
					'rate' => '1'
				]
			],
			[
				'slug' => 'dinner',
				'groups' => [
					'get-takeout',
					'control'
				],
				'sample' => [
					'rate' => '0.25',
				]
			],
			[
				'slug' => 'dessert',
				'groups' => [
					'control',
					'gelato-scoops'
				],
				'sample' => [
					'rate' => '0.75',
				]
			],
		];
	}
}
