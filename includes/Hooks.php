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

use MediaWiki\Extension\EventStreamConfig\Hooks\GetStreamConfigsHook;

class Hooks implements GetStreamConfigsHook {

	/** @var string */
	public const PRODUCT_METRICS_WEB_BASE_SCHEMA_TITLE = 'analytics/product_metrics/web/base';

	/** @var string */
	public const PRODUCT_METRICS_STREAM_PREFIX = 'product_metrics.';

	/** @var string */
	public const PRODUCT_METRICS_DESTINATION_EVENT_SERVICE = 'eventgate-analytics-external';

	private array $instrumentConfigs;

	public function __construct(
		array $instrumentConfigs
	) {
		$this->instrumentConfigs = $instrumentConfigs;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetStreamConfigs
	 * @param array &$streamConfigs
	 */
	public function onGetStreamConfigs( array &$streamConfigs ): void {
		foreach ( $this->instrumentConfigs as $value ) {
			$streamConfigs[ $this->createStreamConfigKey( $value['slug'] ) ] = [
				'schema_title' => self::PRODUCT_METRICS_WEB_BASE_SCHEMA_TITLE,
				'sample' => [
					'rate' => $value['sample_rate'],
					'unit' => $value['sample_unit'],
				],
				'destination_event_service' => self::PRODUCT_METRICS_DESTINATION_EVENT_SERVICE,
			];
		}
	}

	/**
	 * Builds stream setting key.
	 *
	 * The key is built from an instrument's slug using underscores
	 * instead of dashes and prepended with the product metrics prefix.
	 * See StreamConfig's constructor and StreamConfig::STREAM_SETTING
	 * in the EventStreamConfig extension.
	 *
	 * @param string $instrumentSlug
	 * @return string
	 */
	private function createStreamConfigKey( $instrumentSlug ): string {
		return self::PRODUCT_METRICS_STREAM_PREFIX . str_replace( '-', '_', $instrumentSlug );
	}
}
