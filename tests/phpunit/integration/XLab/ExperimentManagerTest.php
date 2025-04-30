<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Integration\XLab;

use Generator;
use MediaWiki\Extension\MetricsPlatform\XLab\Experiment;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
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
		'subject_ids' => [],
		'sampling_units' => [],
		'overrides' => [],
	];

	private User $user;
	private WebRequest $request;
	private MetricsClient $mockMetricsClient;
	private CentralIdLookup $centralIdLookup;

	public function setUp(): void {
		parent::setUp();

		$this->request = new FauxRequest();
		$this->mockMetricsClient = $this->createMock( MetricsClient::class );

		$this->user = new User();
		$this->user->setName( 'TestUser' );
		$this->user->setId( 123 );

		$this->centralIdLookup = $this->createMock( CentralIdLookup::class );
		$this->centralIdLookup
			->method( 'centralIdFromLocalUser' )
			->with( $this->user )
			->willReturn( 321 );

		$this->setService( 'CentralIdLookup', $this->centralIdLookup );
	}

	/**
	 * @dataProvider provideGetUserExperimentsConfig
	 */
	public function testEnrollUserLoggedInExperiments(
		array $expected,
		array $experiments,
		string $message = ''
	) {
		$experimentManager = new ExperimentManager(
			$experiments,
			false,
			$this->mockMetricsClient,
			$this->centralIdLookup
		);

		$experimentManager->enrollUser( $this->user, $this->request );

		$actual = $experimentManager->getExperimentEnrollments();

		$this->assertArrayEquals( $expected, $actual, false, false, $message );
	}

	/**
	 * @dataProvider provideGetLoggedInAndEveryoneExperiments
	 */
	public function testEnrollUserLoggedInAndEveryoneExperiments(
		array $expected,
		array $experiments,
		string $experimentEnrollmentsHeader,
		string $message = ''
	) {
		$experimentManager = new ExperimentManager(
			$experiments,
			false,
			$this->mockMetricsClient,
			$this->centralIdLookup
		);
		$this->request->setHeader( 'X-Experiment-Enrollments', $experimentEnrollmentsHeader );
		$experimentManager->enrollUser( $this->user, $this->request );

		$actual = $experimentManager->getExperimentEnrollments();

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
					'fruit' => '4bcee043d97861cf078d83f45ab8946afd951d5a8c72dc3925ce14ab6b119e5b'
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
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'gelato-scoops',
				],
				'subject_ids' => [
					'fruit' => '4bcee043d97861cf078d83f45ab8946afd951d5a8c72dc3925ce14ab6b119e5b',
					'dessert' => 'bc65059b1450a53402144d102c49cbc2ca6ca4a0d4a9e558fdf4da7969af8901',
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user',
				],
				'overrides' => [],
			],
			static::getMultipleExperimentConfigs(),
			'User is enrolled in multiple experiments',
		];
	}

	public static function provideGetLoggedInAndEveryoneExperiments(): Generator {
		yield [
			[
				'active_experiments' => [
					'experiment_1',
					'experiment_2'
				],
				'enrolled' => [
					'experiment_1',
					'experiment_2'
				],
				'assigned' => [
					'experiment_1' => 'group_1',
					'experiment_2' => 'group_2'
				],
				'subject_ids' => [],
				'sampling_units' => [
					'experiment_1' => 'edge-unique',
					'experiment_2' => 'edge-unique'
				],
				'overrides' => [],
			],
			[],
			'experiment_1=group_1;experiment_2=group_2',
		];

		yield [
			[
				'active_experiments' => [
					'experiment_1',
					'experiment_2'
				],
				'enrolled' => [
					'experiment_1',
					'experiment_2'
				],
				'assigned' => [
					'experiment_1' => 'group_1',
					'experiment_2' => 'group_2'
				],
				'subject_ids' => [],
				'sampling_units' => [
					'experiment_1' => 'edge-unique',
					'experiment_2' => 'edge-unique'
				],
				'overrides' => [],
			],
			[
				[
					'slug' => 'dog-breeds',
					'sample' => [
						'rate' => 0.0
					],
					'groups' => [],
				],
			],
			'experiment_1=group_1;experiment_2=group_2',
			'Experiment with a sample rate of 0.0 should be filtered.'
		];

		yield [
			[
				'active_experiments' => [
					'experiment_1',
					'experiment_2',
					'fruit'
				],
				'enrolled' => [
					'experiment_1',
					'experiment_2',
					'fruit'
				],
				'assigned' => [
					'experiment_1' => 'group_1',
					'experiment_2' => 'group_2',
					'fruit' => 'mango',
				],
				'subject_ids' => [
					'fruit' => '4bcee043d97861cf078d83f45ab8946afd951d5a8c72dc3925ce14ab6b119e5b'
				],
				'sampling_units' => [
					'experiment_1' => 'edge-unique',
					'experiment_2' => 'edge-unique',
					'fruit' => 'mw-user',
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
			'experiment_1=group_1;experiment_2=group_2',
			'User is enrolled in three experiments'
		];

		yield [
			[
				'active_experiments' => [
					'experiment_1',
					'experiment_2',
					'dinner',
				],
				'enrolled' => [
					'experiment_1',
					'experiment_2'
				],
				'assigned' => [
					'experiment_1' => 'group_1',
					'experiment_2' => 'group_2'
				],
				'subject_ids' => [],
				'sampling_units' => [
					'experiment_1' => 'edge-unique',
					'experiment_2' => 'edge-unique'
				],
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
			'experiment_1=group_1;experiment_2=group_2',
			"User isn't enrolled in the logged in experiment but they are in the everyone experiments",
		];

		yield [
			[
				'active_experiments' => [
					'experiment_1',
					'experiment_2',
					'fruit',
					'dinner',
					'dessert',
				],
				'enrolled' => [
					'experiment_1',
					'experiment_2',
					'fruit',
					'dessert',
				],
				'assigned' => [
					'experiment_1' => 'group_1',
					'experiment_2' => 'group_2',
					'fruit' => 'tropical',
					'dessert' => 'gelato-scoops',
				],
				'subject_ids' => [
					'fruit' => '4bcee043d97861cf078d83f45ab8946afd951d5a8c72dc3925ce14ab6b119e5b',
					'dessert' => 'bc65059b1450a53402144d102c49cbc2ca6ca4a0d4a9e558fdf4da7969af8901',
				],
				'sampling_units' => [
					'experiment_1' => 'edge-unique',
					'experiment_2' => 'edge-unique',
					'fruit' => 'mw-user',
					'dessert' => 'mw-user',
				],
				'overrides' => [],
			],
			static::getMultipleExperimentConfigs(),
			'experiment_1=group_1;experiment_2=group_2',
			'User is enrolled in multiple experiments (logged-in and everyone)',
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
					'fruit' => '4bcee043d97861cf078d83f45ab8946afd951d5a8c72dc3925ce14ab6b119e5b'
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
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'gelato-scoops',
				],
				'subject_ids' => [
					'fruit' => '4bcee043d97861cf078d83f45ab8946afd951d5a8c72dc3925ce14ab6b119e5b',
					'dessert' => 'bc65059b1450a53402144d102c49cbc2ca6ca4a0d4a9e558fdf4da7969af8901',
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user',
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
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'gelato-scoops',
				],
				'subject_ids' => [
					'fruit' => '4bcee043d97861cf078d83f45ab8946afd951d5a8c72dc3925ce14ab6b119e5b',
					'dessert' => 'bc65059b1450a53402144d102c49cbc2ca6ca4a0d4a9e558fdf4da7969af8901',
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user',
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
					'dessert'
				],
				'assigned' => [
					'fruit' => 'tropical',
					'dessert' => 'gelato-scoops',
				],
				'subject_ids' => [
					'fruit' => '4bcee043d97861cf078d83f45ab8946afd951d5a8c72dc3925ce14ab6b119e5b',
					'dessert' => 'bc65059b1450a53402144d102c49cbc2ca6ca4a0d4a9e558fdf4da7969af8901',
				],
				'sampling_units' => [
					'fruit' => 'mw-user',
					'dessert' => 'mw-user',
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
			$this->mockMetricsClient,
			$this->centralIdLookup
		);
		$experimentManager->enrollUser( $this->user, $this->request );

		$actual = $experimentManager->getExperimentEnrollments();

		$this->assertArrayEquals( $expected, $actual, false, false, $message );
	}

	public function testSetGetExperimentEnrollments() {
		$experimentManager = new ExperimentManager(
			$this->getMultipleExperimentConfigs(),
			false,
			$this->mockMetricsClient,
			$this->centralIdLookup
		);

		$experimentManager->enrollUser( $this->user, $this->request );

		$actualEnrollmentConfigs = $experimentManager->getExperimentEnrollments();
		$this->assertEquals( $experimentManager->getExperimentEnrollments(), $actualEnrollmentConfigs );
	}

	public function testGetExperiment() {
		$experimentManager = new ExperimentManager(
			$this->getMultipleExperimentConfigs(),
			false,
			$this->mockMetricsClient,
			$this->centralIdLookup
		);

		$experimentManager->enrollUser( $this->user, $this->request );

		$actualExperiment = $experimentManager->getExperiment( 'dessert' );

		$expectedExperiment = new Experiment(
			$this->mockMetricsClient,
			[
				'enrolled' => 'dessert',
				'assigned' => 'gelato-scoops',
				'subject_id' => 'bc65059b1450a53402144d102c49cbc2ca6ca4a0d4a9e558fdf4da7969af8901',
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
