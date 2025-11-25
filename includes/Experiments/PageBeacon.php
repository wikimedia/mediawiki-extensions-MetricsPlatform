<?php

namespace MediaWiki\Extension\MetricsPlatform\Experiments;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManagerInterface;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Html\Html;

class PageBeacon implements BeforePageDisplayHook {
	private const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableExperiments',
		'MetricsPlatformEnableHeadPixel',
		'MetricsPlatformHeadPixelMetric'

		// MetricsPlatform doesn't and can't depend on WikimediaEvents as that would be
		// circular. We'll test for WMEStatsdBaseUri in the hook.
		// WMEStatsdBaseUri
	];

	public const XLAB_EXPERIMENT_NAME_FOR_HEAD_PIXEL = 'xlab-mw-module-loaded-v2';

	private ServiceOptions $options;
	private Config $config;
	private ExperimentManagerInterface $experimentManager;

	public function __construct(
		Config $config,
		ExperimentManagerInterface $experimentManager
	) {
		$this->options = new ServiceOptions(
			self::CONSTRUCTOR_OPTIONS,
			$config
		);
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->config = $config;
		$this->experimentManager = $experimentManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if (
			!$this->options->get( 'MetricsPlatformEnableExperiments' ) ||
			!$this->options->get( 'MetricsPlatformEnableHeadPixel' ) ||
			!$this->options->get( 'MetricsPlatformHeadPixelMetric' ) ||
			!$this->config->has( 'WMEStatsdBaseUri' ) ||
			!$this->config->get( 'WMEStatsdBaseUri' )
		) {
			return;
		}

		// Add beacon only if the user is enrolled and sampled.
		$experiment = $this->experimentManager->getExperiment( self::XLAB_EXPERIMENT_NAME_FOR_HEAD_PIXEL );
		if ( !$experiment->isAssignedGroup( 'control', 'treatment' ) ) {
			return;
		}

		// Add beacon only on view action
		if ( !$out->isPrintable() && $out->getActionName() === 'view' ) {
			// Add HEAD PIXEL (unlabeled, early)
			$metric = $this->options->get( 'MetricsPlatformHeadPixelMetric' );
			$statsdBaseUri = $this->config->get( 'WMEStatsdBaseUri' );
			$img = Html::element( 'img', [
				// https://gerrit.wikimedia.org/r/plugins/gitiles/performance/statsv/+/refs/heads/master/statsv.py#209
				// shows examples of metrics for Prometheus counters
				'src' => $statsdBaseUri . '?' . rawurlencode( $metric ) . ':' . rawurlencode( '1|c' ),
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
