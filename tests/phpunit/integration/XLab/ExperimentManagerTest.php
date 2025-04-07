<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Integration\XLab;

use Generator;
use MediaWiki\Extension\MetricsPlatform\XLab\Experiment;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\MetricsPlatform\MetricsClient;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager
 */
class ExperimentManagerTest extends MediaWikiIntegrationTestCase {

	private const NULL_RESULT = [
		'active_experiments' => [],
		'assigned' => [],
		'enrolled' => [],
		'overrides' => [],
		'sampling_units' => [],
		'subject_ids' => [],
	];

	private UserIdentity $user;
	private WebRequest $request;
	private MetricsClient $mockMetricsClient;

	public function setUp(): void {
		parent::setUp();

		$this->user = new UserIdentityValue( 123, self::class );
		$this->request = new FauxRequest();
		$this->mockMetricsClient = $this->createMock( MetricsClient::class );
	}

	/**
	 * @dataProvider provideGetUserExperimentsConfig
	 */
	public function testEnrollUser(
		array $expected,
		array $experiments,
		string $message = ''
	) {
		$experiment = new ExperimentManager(
			$experiments,
			false,
			$this->mockMetricsClient
		);
		$experiment->enrollUser( $this->user, $this->request );

		$actual = $experiment->getExperimentEnrollments();

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
				'active_experiments' => [
					'fruit'
				],
				'enrolled' => [
					'fruit'
				],
				'assigned' => [
					'fruit' => 'mango'
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4'
				],
				'sampling_units' => [
					'fruit' => 'mw-user'
				],
				'overrides' => [],
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
				'overrides' => [],
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
				'active_experiments' => [
					'fruit',
					'dinner',
					'dessert'
				],
				'enrolled' => [
					'fruit',
					'dinner',
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'control',
					'dinner' => 'get-takeout'
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4',
					'dessert' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
					'dinner' => '36b2f9b733a701393d8d3e9a9cc2f2bb83de54121d8eeff73936cb1fb4911513',
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user',
					'dinner' => 'mw-user',
				],
				'overrides' => [],
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
				'active_experiments' => [
					'fruit'
				],
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
				'overrides' => [
					'fruit'
				],
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
				'active_experiments' => [
					'fruit',
					'dinner',
					'dessert'
				],
				'enrolled' => [
					'fruit',
					'dinner',
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'gelato-scoops',
					'dinner' => 'get-takeout'
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4',
					'dessert' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
					'dinner' => '36b2f9b733a701393d8d3e9a9cc2f2bb83de54121d8eeff73936cb1fb4911513',
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user',
					'dinner' => 'mw-user',
				],
				'overrides' => [
					'fruit',
					'dessert'
				],
			],
			$multipleExperimentConfigs,
			'dessert:gelato-scoops;fruit:tropical',
			'User is enrolled in multiple experiments with one overridden group',
		];

		yield [
			[
				'active_experiments' => [
					'fruit',
					'dinner',
					'dessert'
				],
				'enrolled' => [
					'fruit',
					'dinner',
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'gelato-scoops',
					'dinner' => 'get-takeout'
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4',
					'dessert' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
					'dinner' => '36b2f9b733a701393d8d3e9a9cc2f2bb83de54121d8eeff73936cb1fb4911513',
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user',
					'dinner' => 'mw-user',
				],
				'overrides' => [
					'fruit',
					'dessert'
				],
			],
			$multipleExperimentConfigs,
			'fruit:control;fruit:tropical;dessert:gelato-scoops',
			'User is enrolled in multiple experiments with multiple overridden groups',
		];

		yield [
			[
				'active_experiments' => [
					'fruit',
					'dinner',
					'dessert'
				],
				'enrolled' => [
					'fruit',
					'dinner',
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'control',
					'dinner' => 'get-takeout',
				],
				'subject_ids' => [
					'fruit' => '703dc15f402f02921d844ec4e998ce285ac95f71596cc11f24266922017b8dd4',
					'dessert' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
					'dinner' => '36b2f9b733a701393d8d3e9a9cc2f2bb83de54121d8eeff73936cb1fb4911513',
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user',
					'dinner' => 'mw-user',
				],
				'overrides' => [
					'fruit'
				],
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

		$experimentManager = new ExperimentManager(
			$experiments,
			true,
			$this->mockMetricsClient
		);
		$experimentManager->enrollUser( $this->user, $this->request );

		$actual = $experimentManager->getExperimentEnrollments();

		$this->assertArrayEquals( $expected, $actual, false, false, $message );
	}

	public function testSetGetExperimentEnrollments() {
		$experimentManager = new ExperimentManager(
			$this->getMultipleExperimentConfigs(),
			false,
			$this->mockMetricsClient );
		$experimentManager->enrollUser( $this->user, $this->request );
		$actualEnrollmentConfigs = $experimentManager->getExperimentEnrollments();
		$this->assertEquals( $experimentManager->getExperimentEnrollments(), $actualEnrollmentConfigs );
	}

	public function testGetExperiment() {
		$experimentManager = new ExperimentManager(
			$this->getMultipleExperimentConfigs(),
			false,
			$this->mockMetricsClient );
		$experimentManager->enrollUser( $this->user, $this->request );
		$actualExperiment = $experimentManager->getExperiment( 'dessert' );

		$expectedExperiment = new Experiment(
			$this->mockMetricsClient,
			[
				'enrolled' => 'dessert',
				'assigned' => 'control',
				'subject_id' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
				'sampling_unit' => 'mw-user',
				'coordinator' => 'xLab'
			]
		);
		$this->assertEquals( $expectedExperiment, $actualExperiment );
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
					'rate' => '0.45',
				]
			],
			[
				'slug' => 'dessert',
				'groups' => [
					'control',
					'gelato-scoops'
				],
				'sample' => [
					'rate' => '0.8',
				]
			],
		];
	}
}
