<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Html\Html;
use Wikimedia\Assert\Assert;

class PageBeacon implements BeforePageDisplayHook {
	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableHeadPixel'
	];

	public function __construct(
		private readonly Config $config,
	) {
		Assert::parameter(
			$config->has( 'MetricsPlatformEnableHeadPixel' ),
			'$config',
			'Required config "MetricsPlatformEnableHeadPixel" missing.'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->config->get( 'MetricsPlatformEnableExperiments' ) ||
				!$this->config->get( 'MetricsPlatformEnableHeadPixel' )
		) {
			return;
		}

		// Add beacon only on view action
		if ( !$out->isPrintable() &&
			$out->getRequest()->getVal( 'action', 'view' ) === 'view'
		) {
			// Add HEAD PIXEL (unlabeled, early)
			$metric = $this->config->get( 'MetricsPlatformHeadPixelMetric' );
			$img = Html::element( 'img', [
				'src' => '/beacon/statsv?' . rawurlencode( $metric ) . '=1',
				'alt' => '',
				'aria-hidden' => 'true',
				'width' => '1',
				'height' => '1',
				'decoding' => 'async',
				'referrerpolicy' => 'no-referrer',
				'style' => 'position:absolute;left:-9999px;'
			] );
			$out->addHeadItem( 'mpl-head-pixel', $img );
		}
	}
}
