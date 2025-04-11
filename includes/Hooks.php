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

class Hooks implements
	GetStreamConfigsHook
{
	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableStreamConfigsFetching',
		'MetricsPlatformEnableStreamConfigsMerging',
	];

	/** @var string */
	public const PRODUCT_METRICS_WEB_BASE_SCHEMA_TITLE = 'analytics/product_metrics/web/base';

	/** @var string */
	public const PRODUCT_METRICS_DESTINATION_EVENT_SERVICE = 'eventgate-analytics-external';

	private InstrumentConfigsFetcher $configsFetcher;
	private ServiceOptions $options;

	public static function newInstance(
		Config $config,
		InstrumentConfigsFetcher $configsFetcher
	): self {
		return new self(
			new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config ),
			$configsFetcher
		);
	}

	public function __construct(
		ServiceOptions $options,
		InstrumentConfigsFetcher $configsFetcher
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->configsFetcher = $configsFetcher;
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
}
