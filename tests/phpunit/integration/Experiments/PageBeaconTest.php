<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Integration\XLab;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\MetricsPlatform\Experiments\PageBeacon;
use MediaWiki\Extension\MetricsPlatform\XLab\Experiment;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;

/**
 * @group Database
 * @covers \MediaWiki\Extension\MetricsPlatform\Experiments\PageBeacon
 */
class PageBeaconTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	private OutputPage $output;
	private RequestContext $context;
	private ExperimentManager $mockExperimentManager;
	private Experiment $mockExperiment;

	public function setUp(): void {
		parent::setUp();

		$config = [
			'MetricsPlatformEnableExperiments' => true,
			'MetricsPlatformEnableHeadPixel' => true,
			'MetricsPlatformHeadPixelMetric' => 'mediawiki_page_load_head_pixel_total',
			'WMEStatsdBaseUri' => '/beacon/statsv'
		];
		$this->overrideConfigValues( $config );
		$this->context = new RequestContext();
		$this->output = new OutputPage( $this->context );

		$this->mockExperimentManager = $this->createMock( ExperimentManager::class );
		$this->mockExperiment = $this->createMock( Experiment::class );
		$this->mockExperiment->method( 'isAssignedGroup' )
			->willReturn( true );
		$this->mockExperimentManager->method( 'getExperiment' )
			->willReturn( $this->mockExperiment );
	}

	public function testBeforePageDisplay_addsHeadPixelAndModuleOnView() {
		$this->context->setRequest( new FauxRequest( [ 'action' => 'view' ], true ) );
		$this->output->setContext( $this->context );
		$services = $this->getServiceContainer();
		( new PageBeacon(
			$services->getMainConfig(),
			$this->mockExperimentManager,
		) )->onBeforePageDisplay( $this->output, $this->output->getSkin() );

		// Head items contain the pixel with correct metric
		$headHtml = implode( '', $this->output->getHeadItemsArray() );
		$this->assertStringContainsString(
			'/beacon/statsv?mediawiki_page_load_head_pixel_total:1%7Cc',
			$headHtml,
			'Head pixel <img> points at statsv with metric name and :1|c'
		);
	}

	public function testBeforePageDisplay_skipsWhenPrintable() {
		// Printable: head pixel and module should be skipped
		$this->context->setRequest( new FauxRequest( [ 'action' => 'view' ] ) );
		$this->output->setContext( $this->context );
		$this->output->setTitle( Title::newMainPage() );
		$this->output->setPrintable();

		$services = $this->getServiceContainer();

		( new PageBeacon(
			$services->getMainConfig(),
			$this->mockExperimentManager,
		) )->onBeforePageDisplay( $this->output, $this->output->getSkin() );

		$this->assertStringNotContainsString(
			'/beacon/statsv?',
			implode( '', $this->output->getHeadItemsArray() ),
			'Head pixel not added on printable'
		);
	}

	public function testBeforePageDisplay_skipsWhenEdit() {
		// action=edit: RL module should not be added; head pixel also skipped
		$this->context->setRequest( new FauxRequest( [ 'action' => 'edit' ], true ) );
		$this->output->setContext( $this->context );
		$this->output->setTitle( Title::newMainPage() );

		$services = $this->getServiceContainer();

		( new PageBeacon(
			$services->getMainConfig(),
			$this->mockExperimentManager,
		) )->onBeforePageDisplay( $this->output, $this->output->getSkin() );

		$this->assertStringNotContainsString(
			'/beacon/statsv?',
			implode( '', $this->output->getHeadItemsArray() ),
			'Head pixel not added on action=edit'
		);
	}

	public function testBeforePageDisplay_userNotInTreatment() {
		$this->context->setRequest( new FauxRequest( [ 'action' => 'view' ], true ) );
		$this->output->setContext( $this->context );

		$mockExperimentManager = $this->createMock( ExperimentManager::class );
		$mockExperiment = $this->createMock( Experiment::class );
		$mockExperiment->method( 'isAssignedGroup' )
			->willReturn( false );
		$mockExperimentManager->method( 'getExperiment' )
			->willReturn( $mockExperiment );

		$services = $this->getServiceContainer();
		( new PageBeacon(
			$services->getMainConfig(),
			$mockExperimentManager,
		) )->onBeforePageDisplay( $this->output, $this->output->getSkin() );

		// Head items contain the pixel with correct metric
		$headHtml = implode( '', $this->output->getHeadItemsArray() );
		$this->assertStringNotContainsString(
			'/beacon/statsv?',
			implode( '', $this->output->getHeadItemsArray() ),
			'Head pixel not added when user not in sample'
		);
	}
}
