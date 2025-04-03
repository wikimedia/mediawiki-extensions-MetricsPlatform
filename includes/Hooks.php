<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\EventStreamConfig\Hooks\GetStreamConfigsHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use Skin;

class Hooks implements
	GetStreamConfigsHook,
	BeforePageDisplayHook
{
	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableStreamConfigsFetching',
		'MetricsPlatformEnableStreamConfigsMerging',
		'MetricsPlatformEnableExperiments',
		'MetricsPlatformEnableExperimentOverrides',
	];

	/** @var string */
	public const PRODUCT_METRICS_WEB_BASE_SCHEMA_TITLE = 'analytics/product_metrics/web/base';

	/** @var string */
	public const PRODUCT_METRICS_STREAM_PREFIX = 'product_metrics.';

	/** @var string */
	public const PRODUCT_METRICS_DESTINATION_EVENT_SERVICE = 'eventgate-analytics-external';

	private InstrumentConfigsFetcher $configsFetcher;
	private ExperimentManagerFactory $experimentManagerFactory;
	private ServiceOptions $options;

	public static function newInstance(
		InstrumentConfigsFetcher $configsFetcher,
		ExperimentManagerFactory $experimentManagerFactory,
		Config $config
	): self {
		return new self(
			$configsFetcher,
			$experimentManagerFactory,
			new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config )
		);
	}

	public function __construct(
		InstrumentConfigsFetcher $configsFetcher,
		ExperimentManagerFactory $experimentManagerFactory,
		ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->configsFetcher = $configsFetcher;
		$this->experimentManagerFactory = $experimentManagerFactory;
		$this->options = $options;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetStreamConfigs
	 * @param array &$streamConfigs
	 */
	public function onGetStreamConfigs( array &$streamConfigs ): void {
		if ( !$this->options->get( 'MetricsPlatformEnableStreamConfigsFetching' ) ) {
			return;
		}

		$instrumentConfigs = $this->configsFetcher->getInstrumentConfigs();

		if ( $this->options->get( 'MetricsPlatformEnableStreamConfigsMerging' ) ) {
			foreach ( $instrumentConfigs as $value ) {
				$streamConfigs[ $value['stream_name'] ] = [
					'schema_title' => self::PRODUCT_METRICS_WEB_BASE_SCHEMA_TITLE,
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => $value['contextual_attributes']
						]
					],
					'sample' => $value['sample'],
					'destination_event_service' => self::PRODUCT_METRICS_DESTINATION_EVENT_SERVICE,
				];
			}
		}
	}

	/**
	 * This hook adds a JavaScript configuration variable to the output.
	 *
	 * In order to provide experiment enrollment data with bucketing assignments
	 * for a logged-in user, we take the user's id to deterministically sample and
	 * bucket the user. Based on the sample rates of active experiments, the user's
	 * participation in experimentation cohorts is written to a configuration variable
	 * that will be read by the Metrics Platform client libraries and instrument code
	 * to send that data along during events submission.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		// Skip if:
		//
		// 1. Experiments are disabled; or
		// 2. The user is not logged in or is a temporary user.
		if (
			!$this->options->get( 'MetricsPlatformEnableExperiments' ) ||
			!$out->getUser()->isNamed()
		) {
			return;
		}
		$services = MediaWikiServices::getInstance();

		// Get the user's central ID (for assigning buckets later) and skip if 0.
		$lookup = $services->getCentralIdLookupFactory()->getLookup();
		$userId = $lookup->centralIdFromLocalUser( $out->getUser() );
		if ( $userId === 0 ) {
			return;
		}

		$experimentManager = $this->experimentManagerFactory->newInstance();

		// Set the JS config variable for the user's experiment enrollment data.
		$out->addJsConfigVars(
			'wgMetricsPlatformUserExperiments',
			$experimentManager->enrollUser( $out->getUser(), $out->getRequest() )
		);

		// Optimization:
		//
		// The `ext.metricsPlatform` module only contains a QA-related function right now. Only send the module to the
		// browser when we allow experiment enrollment overrides.
		if ( $this->options->get( 'MetricsPlatformEnableExperimentOverrides' ) ) {
			$out->addModules( 'ext.metricsPlatform' );
		}

		// The `ext.xLab` module contains the JS xLab SDK that is the API the feature code will use to get
		// the experiments and the corresponding assigned group for the current user
		$out->addModules( 'ext.xLab' );
	}
}
