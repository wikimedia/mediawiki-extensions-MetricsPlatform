<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\MetricsPlatform\XLab\PageBeacon;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;

class PageBeaconTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	private OutputPage $output;
	private RequestContext $context;

	public function setUp(): void {
		parent::setUp();

		$this->overrideConfigValues( [
			'MetricsPlatformEnableExperiments' => true,
			'MetricsPlatformEnableHeadPixel' => true,
			'MetricsPlatformHeadPixelMetric' => 'counter.MediaWiki.PageLoadedHead'
		] );
		$this->context = new RequestContext();
		$this->output = new OutputPage( $this->context );
	}

	/**
	 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\PageBeacon
	 */
	public function testBeforePageDisplay_addsHeadPixelAndModuleOnView() {
		$this->context->setRequest( new FauxRequest( [ 'action' => 'view' ], true ) );
		$this->output->setContext( $this->context );
		$services = $this->getServiceContainer();
		( new PageBeacon(
			$services->getMainConfig()
		) )->onBeforePageDisplay( $this->output, $this->output->getSkin() );

		// Head items contain the pixel with correct metric
		$headHtml = implode( '', $this->output->getHeadItemsArray() );
		$this->assertStringContainsString(
			'/beacon/statsv?counter.MediaWiki.PageLoadedHead=1',
			$headHtml,
			'Head pixel <img> points at statsv with metric name and =1'
		);
	}

	/**
	 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\PageBeacon
	 */
	public function testBeforePageDisplay_skipsWhenPrintable() {
		// Printable: head pixel and module should be skipped
		$this->context->setRequest( new FauxRequest( [ 'action' => 'view' ] ) );
		$this->output->setContext( $this->context );
		$this->output->setTitle( Title::newMainPage() );
		$this->output->setPrintable();

		$services = $this->getServiceContainer();

		( new PageBeacon(
			$services->getMainConfig()
		) )->onBeforePageDisplay( $this->output, $this->output->getSkin() );

		$this->assertStringNotContainsString(
			'/beacon/statsv?',
			implode( '', $this->output->getHeadItemsArray() ),
			'Head pixel not added on printable'
		);
	}

	/**
	 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\PageBeacon
	 */
	public function testBeforePageDisplay_skipsWhenEdit() {
		// action=edit: RL module should not be added; head pixel also skipped
		$this->context->setRequest( new FauxRequest( [ 'action' => 'edit' ], true ) );
		$this->output->setContext( $this->context );
		$this->output->setTitle( Title::newMainPage() );

		$services = $this->getServiceContainer();

		( new PageBeacon(
			$services->getMainConfig()
		) )->onBeforePageDisplay( $this->output, $this->output->getSkin() );

		$this->assertStringNotContainsString(
			'/beacon/statsv?',
			implode( '', $this->output->getHeadItemsArray() ),
			'Head pixel not added on action=edit'
		);
	}
}
