<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\MetricsPlatform\XLab\Hooks;
use MediaWiki\MediaWikiEntryPoint;
use MediaWiki\Output\OutputPage;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use User;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Hooks
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentRequest
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentAuthority
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EveryoneExperimentsEnrollmentAuthority
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\LoggedInExperimentsEnrollmentAuthority
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\OverridesEnrollmentAuthority
 */
class HooksTest
	extends MediaWikiIntegrationTestCase
{
	use MockHttpTrait;

	private const NO_ENROLLMENTS = [
		'active_experiments' => [],
		'enrolled' => [],
		'assigned' => [],
		'subject_ids' => [],
		'sampling_units' => [],
		'overrides' => []
	];
	private CentralIdLookup $centralIdLookup;
	private RequestContext $context;
	private OutputPage $output;
	private MediaWikiEntryPoint $entryPoint;

	public function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			'MetricsPlatformEnableExperiments' => true,
			'MetricsPlatformEnableExperimentOverrides' => true,
			'MetricsPlatformEnableExperimentConfigsFetching' => true,
			'MetricsPlatformEnableStreamConfigsFetching' => false,
			'MetricsPlatformEnableStreamConfigsMerging' => false,
		] );

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		// logged-in-experiment-3 shouldn't be considered (it hasn't started yet)
		$this->installMockHttp( $this->makeFakeHttpRequest( '[
			{
    			"name": "logged-in-experiment-1",
    			"start": "' . $now->modify( '-1 month' )->format( 'Y-m-d\TH:i:s\Z' ) . '",
    			"end": "' . $now->modify( '+1 month' )->format( 'Y-m-d\TH:i:s\Z' ) . '",
    			"sample_rate": {
    	  			"default": 1
     			},
     			"groups": [ "control", "group-something" ]
			},
			{
    			"name": "logged-in-experiment-2",
    			"start": "' . $now->modify( '-1 week' )->format( 'Y-m-d\TH:i:s\Z' ) . '",
    			"end": "' . $now->modify( '+1 week' )->format( 'Y-m-d\TH:i:s\Z' ) . '",
    			"sample_rate": {
    	  			"default": 1
     			},
     			"groups": [ "control", "group-other-thing" ]
			},
			{
    			"name": "logged-in-experiment-3",
    			"start": "' . $now->modify( '+1 week' )->format( 'Y-m-d\TH:i:s\Z' ) . '",
    			"end": "' . $now->modify( '+2 week' )->format( 'Y-m-d\TH:i:s\Z' ) . '",
    			"sample_rate": {
    	  			"default": 1
     			},
     			"groups": [ "control", "group-another-thing" ]
			}
		]' ) );

		// CentralIdLookup service
		$this->centralIdLookup = $this->createMock( CentralIdLookup::class );
		$this->setService( 'CentralIdLookup', $this->centralIdLookup );

		$this->resetServices();
		$this->context = new RequestContext();
		$this->output = new OutputPage( $this->context );
		$this->entryPoint = $this->createMock( ActionEntryPoint::class );
	}

	private function onBeforeInitialize(): array {
		$services = $this->getServiceContainer();

		$hooks = new Hooks(
			$services->getMainConfig(),
			$services->getService( 'MetricsPlatform.ConfigsFetcher' ),
			$services->getService( 'MetricsPlatform.XLab.EnrollmentAuthority' ),
			$services->getService( 'MetricsPlatform.XLab.ExperimentManager' )
		);

		$hooks->onBeforeInitialize(
			$this->context->getTitle(),
			null,
			$this->output,
			$this->context->getUser(),
			$this->context->getRequest(),
			$this->entryPoint
		);

		return $this->output->getJsConfigVars()['wgMetricsPlatformUserExperiments'];
	}

	/**
	 * Tests `X-Experiment-Enrollments` header when only everyone experiments are running
	 */
	public function testOnlyEveryoneExperiments(): void {
		$this->context->getRequest()->setHeader(
			'X-Experiment-Enrollments',
			'experiment_1=group_1;experiment_2=group_2'
		);

		$actual = $this->onBeforeInitialize();
		$expected = [
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
			'subject_ids' => [
				'experiment_1' => 'awaiting',
				'experiment_2' => 'awaiting',
			],
			'sampling_units' => [
				'experiment_1' => 'edge-unique',
				'experiment_2' => 'edge-unique'
			],
			'overrides' => []
		];

		$this->assertEquals(
			$expected,
			$actual,
			'X-Experiment-Enrollments header has been parsed correctly'
		);
	}

	/**
	 * Tests `X-Experiment-Enrollments` header when everyone and logged-in experiments are running
	 */
	public function testEveryoneAndLoggedInExperiments(): void {
		$this->context->getRequest()->setHeader(
			'X-Experiment-Enrollments',
			'experiment_1=group_1;experiment_2=group_2'
		);

		// The user is registered (they have a local ID > 0) and they have a central user ID > 0
		// as well.
		$user = new User();
		$user->setName( 'TestUser' );
		$user->setId( 123 );
		$this->context->setUser( $user );

		$this->centralIdLookup->expects( $this->once() )
			->method( 'centralIdFromLocalUser' )
			->with( $user )
			->willReturn( 321 );

		$actual = $this->onBeforeInitialize();
		$expected = [
			'active_experiments' => [
				'experiment_1',
				'experiment_2',
				'logged-in-experiment-1',
				'logged-in-experiment-2'
			],
			'enrolled' => [
				'experiment_1',
				'experiment_2',
				'logged-in-experiment-1',
				'logged-in-experiment-2'
			],
			'assigned' => [
				'experiment_1' => 'group_1',
				'experiment_2' => 'group_2',
				'logged-in-experiment-1' => 'group-something',
				'logged-in-experiment-2' => 'group-other-thing',
			],
			'subject_ids' => [
				'experiment_1' => 'awaiting',
				'experiment_2' => 'awaiting',
				'logged-in-experiment-1' => '9b6a4e7d98cd96a463fbcadb9e9edfdd9e4b5d9560c79b9d16b38599cb23128e',
				'logged-in-experiment-2' => '94d25f0b2bd16c79bad41a0b9713a604e6b709ffe30124f5bb68bcae9d57ba38'
			],
			'sampling_units' => [
				'experiment_1' => 'edge-unique',
				'experiment_2' => 'edge-unique',
				'logged-in-experiment-1' => 'mw-user',
				'logged-in-experiment-2' => 'mw-user'
			],
			'overrides' => []
		];

		$this->assertEquals(
			$expected,
			$actual,
			'X-Experiment-Enrollments header has been parsed correctly'
		);
	}

	/**
	 * Tests whether the `X-Experiment-Enrollments` header is malformed
	 */
	public function testHeaderIsMalformed(): void {
		$this->context->getRequest()->setHeader( 'X-Experiment-Enrollments', 'something-is-wrong-here' );

		$this->assertEquals(
			self::NO_ENROLLMENTS,
			$this->onBeforeInitialize(),
			'X-Experiment-Enrollments header is malformed and has been considered as empty'
		);
	}

	/**
	 * Tests whether the `X-Experiment-Enrollments` header is partially malformed
	 */
	public function testHeaderIsPartiallyMalformed(): void {
		$this->context->getRequest()->setHeader(
			'X-Experiment-Enrollments',
			'experiment_1=group1;header-is-partially-malformed'
		);

		$this->assertEquals(
			self::NO_ENROLLMENTS,
			$this->onBeforeInitialize(),
			'X-Experiment-Enrollments header is partially malformed and has been considered as empty'
		);
	}

	/**
	 * Tests whether the `X-Experiment-Enrollments` header is not present
	 */
	public function testHeaderIsNotPresent(): void {
		$this->assertEquals(
			self::NO_ENROLLMENTS,
			$this->onBeforeInitialize(),
			'X-Experiment-Enrollments header is not present so there are no enrollments for everyone experiments'
		);
	}

	/**
	 * Tests the user setting an override for an out-sample for a logged-in experiment.
	 */
	public function testOverriddenOutSampleLoggedInExperiment(): void {
		// The user is registered (they have a local ID > 0) and they have a central user ID > 0
		// as well.
		$user = new User();
		$user->setName( 'TestUser' );
		$user->setId( 123 );
		$this->context->setUser( $user );

		$this->centralIdLookup->expects( $this->once() )
			->method( 'centralIdFromLocalUser' )
			->with( $user )
			->willReturn( 321 );

		$this->context->getRequest()->setCookie(
			'mpo', 'logged-in-experiment-3:control'
		);

		$actual = $this->onBeforeInitialize();
		$expected = [
			'active_experiments' => [
				'logged-in-experiment-1',
				'logged-in-experiment-2',
				'logged-in-experiment-3',
			],
			'enrolled' => [
				'logged-in-experiment-1',
				'logged-in-experiment-2',
				'logged-in-experiment-3',
			],
			'assigned' => [
				'logged-in-experiment-1' => 'group-something',
				'logged-in-experiment-2' => 'group-other-thing',
				'logged-in-experiment-3' => 'control',
			],
			'subject_ids' => [
				'logged-in-experiment-1' => '9b6a4e7d98cd96a463fbcadb9e9edfdd9e4b5d9560c79b9d16b38599cb23128e',
				'logged-in-experiment-2' => '94d25f0b2bd16c79bad41a0b9713a604e6b709ffe30124f5bb68bcae9d57ba38',
				'logged-in-experiment-3' => 'overridden',
			],
			'sampling_units' => [
				'logged-in-experiment-1' => 'mw-user',
				'logged-in-experiment-2' => 'mw-user',
				'logged-in-experiment-3' => 'mw-user',
			],
			'overrides' => [
				'logged-in-experiment-3',
			]
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Tests whether the ext.xLab module is added to the output even when the user is not enrolled in any active
	 * experiments.
	 */
	public function testModuleIsAdded(): void {
		$this->onBeforeInitialize();

		$this->assertContains(
			'ext.xLab',
			$this->output->getModules(),
			'The ext.xLab module is added to the output when the user is not enrolled in any active experiments'
		);
	}
}
