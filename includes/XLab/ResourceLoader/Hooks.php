<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab\ResourceLoader;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;

class Hooks {
	private const XLAB_STREAMS = [
		'product_metrics.web_base',
		'mediawiki.product_metrics.translation_mint_for_readers.experiments',
	];

	/**
	 * Gets the contents of the `config.json` file for the `ext.xLab` ResourceLoader module.
	 *
	 * @param RL\Context $context
	 * @param Config $config
	 * @return array
	 */
	public static function getConfigForXLabModule( RL\Context $context, Config $config ): array {
		return [
			'EnableExperimentOverrides' => $config->get( 'MetricsPlatformEnableExperimentOverrides' ),
			'EveryoneExperimentEventIntakeServiceUrl' =>
				$config->get( 'MetricsPlatformExperimentEventIntakeServiceUrl' ),

			// NOTE: MetricsPlatform has a hard dependency on EventLogging. If this code is executing, then
			// EventLogging is loaded and this config variable is defined.
			'LoggedInExperimentEventIntakeServiceUrl' => $config->get( 'EventLoggingServiceUri' ),

			'streamConfigs' => self::getStreamConfigs(),
		];
	}

	/**
	 * Gets the stream configs for those streams that xLab uses (see {@link Hooks::XLAB_STREAMS}).
	 *
	 * Note well that the stream configs are limited copies. The copies only contain the
	 * `producers.metrics_platform_client` property because:
	 *
	 * 1. The Metrics Platform client treats streams as in-sample by default. Therefore, removing the analytics sampling
	 *    config from the copied stream config makes the stream always in-sample
	 *
	 * 2. It minimizes the size of the `ext.xLab` ResourceLoader module.
	 *
	 * @return array|false
	 */
	private static function getStreamConfigs() {
		$streamConfigs = MediaWikiServices::getInstance()->getService( 'EventLogging.StreamConfigs' );

		if ( $streamConfigs === false ) {
			return false;
		}

		$result = [];

		foreach ( self::XLAB_STREAMS as $streamName ) {
			if ( !isset( $streamConfigs[$streamName] ) ) {
				continue;
			}

			$streamConfig = $streamConfigs[$streamName];

			if ( !isset( $streamConfig['producers']['metrics_platform_client'] ) ) {
				return [];
			}

			$result[$streamName] = [
				'producers' => [
					'metrics_platform_client' => $streamConfig['producers']['metrics_platform_client'],
				],
			];
		}

		return $result;
	}
}
