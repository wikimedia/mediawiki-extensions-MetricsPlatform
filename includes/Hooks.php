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
use MediaWiki\MainConfigNames;

class Hooks implements GetStreamConfigsHook {

	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableStreamConfigsMerging',
		MainConfigNames::DBname,
	];

	/** @var string */
	public const PRODUCT_METRICS_WEB_BASE_SCHEMA_TITLE = 'analytics/product_metrics/web/base';

	/** @var string */
	public const PRODUCT_METRICS_STREAM_PREFIX = 'product_metrics.';

	/** @var string */
	public const PRODUCT_METRICS_DESTINATION_EVENT_SERVICE = 'eventgate-analytics-external';

	private array $instrumentConfigs;
	private ServiceOptions $options;

	public static function newInstance( array $instrumentConfigs, Config $config ): self {
		return new self(
			$instrumentConfigs,
			new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config )
		);
	}

	public function __construct( array $instrumentConfigs, ServiceOptions $options ) {
		$this->instrumentConfigs = $instrumentConfigs;

		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetStreamConfigs
	 * @param array &$streamConfigs
	 */
	public function onGetStreamConfigs( array &$streamConfigs ): void {
		if ( !$this->options->get( 'MetricsPlatformEnableStreamConfigsMerging' ) ) {
			return;
		}

		foreach ( $this->instrumentConfigs as $value ) {
			if ( !$value['status'] ) {
				continue;
			}

			$streamConfigs[ $value['stream_name'] ] = [
				'schema_title' => self::PRODUCT_METRICS_WEB_BASE_SCHEMA_TITLE,
				'producers' => [
					'metrics_platform_client' => [
						'provide_values' => $value['contextual_attributes']
					]
				],
				'sample' => $this->getSampleConfig( $value ),
				'destination_event_service' => self::PRODUCT_METRICS_DESTINATION_EVENT_SERVICE,
			];
		}
	}

	/**
	 * @param array $instrumentConfig
	 * @return array
	 */
	private function getSampleConfig( array $instrumentConfig ): array {
		$sampleConfig = [
			'rate' => 0.0,
			'unit' => 'session',
		];

		if ( array_key_exists( 'sample_rate', $instrumentConfig ) ) {
			$sampleRates = $instrumentConfig['sample_rate'];

			$sampleConfig['rate'] = $sampleRates['default'];
			unset( $sampleRates['default'] );

			$dbname = $this->options->get( MainConfigNames::DBname );

			foreach ( $sampleRates as $rate => $wikis ) {
				if ( in_array( $dbname, $wikis ) ) {
					$sampleConfig['rate'] = $rate;

					break;
				}
			}
		}

		if ( array_key_exists( 'sample_unit', $instrumentConfig ) ) {
			$sampleConfig['unit'] = $instrumentConfig['sample_unit'];
		}

		return $sampleConfig;
	}
}
