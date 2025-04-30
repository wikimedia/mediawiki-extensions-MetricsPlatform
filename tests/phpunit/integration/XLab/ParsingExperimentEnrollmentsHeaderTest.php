<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Integration;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\MetricsPlatform\XLab\Hooks;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use User;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Hooks::onBeforePageDisplay
 * @group MetricsPlatform
 */
class ParsingExperimentEnrollmentsHeaderTest
	extends MediaWikiIntegrationTestCase
{
	use MockHttpTrait;

	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableExperiments',
		'MetricsPlatformEnableExperimentOverrides',
		'MetricsPlatformEnableExperimentConfigsFetching'
	];

	private const NO_ENROLLMENTS = [
		'active_experiments' => [],
		'enrolled' => [],
		'assigned' => [],
		'subject_ids' => [],
		'sampling_units' => [],
		'overrides' => []
	];

	private CentralIdLookup $centralIdLookup;

	public function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			'MetricsPlatformEnableExperiments' => true,
			'MetricsPlatformEnableExperimentConfigsFetching' => true,
			'MetricsPlatformEnableStreamConfigsFetching' => false,
			'MetricsPlatformEnableStreamConfigsMerging' => false,
		] );

		$this->installMockHttp( $this->makeFakeHttpRequest( '[
			{
    			"slug": "logged-in-experiment-1",
                "status": 1,
    			"sample_rate": {
    	  			"default": 1
     			},
     			"groups": [ "control", "group-something" ]
			},
			{
    			"slug": "logged-in-experiment-2",
                "status": 1,
    			"sample_rate": {
    	  			"default": 1
     			},
     			"groups": [ "control", "group-other-thing" ]
			}
		]'
		) );

		// CentralIdLookup service
		$this->centralIdLookup = $this->createMock( CentralIdLookup::class );

		$this->setService( 'CentralIdLookup', $this->centralIdLookup );

		// Finally...
		$this->resetServices();
	}

	/**
	 * Tests `X-Experiment-Enrollments` header when only everyone experiments are running
	 */
	public function testOnlyEveryoneExperiments(): void {
		$services = $this->getServiceContainer();

		$context = new RequestContext();
		$context->getRequest()->setHeader( 'X-Experiment-Enrollments', 'experiment_1=group_1;experiment_2=group_2' );
		$out = new OutputPage( $context );
		$skin = new SkinTemplate();
		$hooks = new Hooks(
			new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getService( 'MetricsPlatform.ConfigsFetcher' ),
			$services->getService( 'MetricsPlatform.ExperimentManagerFactory' )
		);
		$hooks->onBeforePageDisplay( $out, $skin );

		$result = $out->getJsConfigVars()['wgMetricsPlatformUserExperiments'];
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
			'subject_ids' => [],
			'sampling_units' => [
				'experiment_1' => 'edge-unique',
				'experiment_2' => 'edge-unique'
			],
			'overrides' => []
		];

		$this->assertEquals(
			$expected,
			$result,
			'X-Experiment-Enrollments header has been parsed correctly'
		);
	}

	/**
	 * Tests `X-Experiment-Enrollments` header when everyone and logged-in experiments are running
	 */
	public function testEveryoneAndLoggedInExperiments(): void {
		$services = $this->getServiceContainer();

		$context = new RequestContext();
		$context->getRequest()->setHeader( 'X-Experiment-Enrollments', 'experiment_1=group_1;experiment_2=group_2' );

		$user = new User();
		$user->setName( 'TestUser' );
		$user->setId( 123 );
		$context->setUser( $user );

		$this->centralIdLookup->expects( $this->once() )
			->method( 'centralIdFromLocalUser' )
			->with( $user )
			->willReturn( 321 );

		$out = new OutputPage( $context );

		$skin = new SkinTemplate();
		$hooks = new Hooks(
			new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getService( 'MetricsPlatform.ConfigsFetcher' ),
			$services->getService( 'MetricsPlatform.ExperimentManagerFactory' )
		);
		$hooks->onBeforePageDisplay( $out, $skin );

		$result = $out->getJsConfigVars()['wgMetricsPlatformUserExperiments'];
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
				'logged-in-experiment-1' => '9b6a4e7d98cd96a463fbcadb9e9edfdd9e4b5d9560c79b9d16b38599cb23128e',
				'logged-in-experiment-2' => '94d25f0b2bd16c79bad41a0b9713a604e6b709ffe30124f5bb68bcae9d57ba38',
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
			$result,
			'X-Experiment-Enrollments header has been parsed correctly'
		);
	}

	/**
	 * Tests whether the `X-Experiment-Enrollments` header is malformed
	 */
	public function testHeaderIsMalformed(): void {
		$services = $this->getServiceContainer();

		$context = new RequestContext();
		$context->getRequest()->setHeader( 'X-Experiment-Enrollments', 'something-is-wrong-here' );
		$out = new OutputPage( $context );
		$skin = new SkinTemplate();
		$hooks = new Hooks(
			new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getService( 'MetricsPlatform.ConfigsFetcher' ),
			$services->getService( 'MetricsPlatform.ExperimentManagerFactory' )
		);
		$hooks->onBeforePageDisplay( $out, $skin );

		$result = $out->getJsConfigVars()['wgMetricsPlatformUserExperiments'];

		$this->assertEquals(
			self::NO_ENROLLMENTS,
			$result,
			'X-Experiment-Enrollments header is malformed and has been considered as empty'
		);
	}

	/**
	 * Tests whether the `X-Experiment-Enrollments` header is partially malformed
	 */
	public function testHeaderIsPartiallyMalformed(): void {
		$services = $this->getServiceContainer();

		$context = new RequestContext();
		$context->getRequest()->setHeader(
			'X-Experiment-Enrollments',
			'experiment_1=group1;header-is-partially-malformed'
		);
		$out = new OutputPage( $context );
		$skin = new SkinTemplate();
		$hooks = new Hooks(
			new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getService( 'MetricsPlatform.ConfigsFetcher' ),
			$services->getService( 'MetricsPlatform.ExperimentManagerFactory' )
		);
		$hooks->onBeforePageDisplay( $out, $skin );

		$result = $out->getJsConfigVars()['wgMetricsPlatformUserExperiments'];

		$this->assertEquals(
			self::NO_ENROLLMENTS,
			$result,
			'X-Experiment-Enrollments header is partially malformed and has been considered as empty'
		);
	}

	/**
	 * Tests whether the `X-Experiment-Enrollments` header is not present
	 */
	public function testHeaderIsNotPresent(): void {
		$services = $this->getServiceContainer();

		$context = new RequestContext();

		$out = new OutputPage( $context );
		$skin = new SkinTemplate();
		$hooks = new Hooks(
			new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getService( 'MetricsPlatform.ConfigsFetcher' ),
			$services->getService( 'MetricsPlatform.ExperimentManagerFactory' )
		);
		$hooks->onBeforePageDisplay( $out, $skin );

		$result = $out->getJsConfigVars()['wgMetricsPlatformUserExperiments'];

		$this->assertEquals(
			self::NO_ENROLLMENTS,
			$result,
			'X-Experiment-Enrollments header is not present so there are no enrollments for everyone experiments'
		);
	}
}
