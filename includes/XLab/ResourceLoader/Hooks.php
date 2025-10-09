<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab\ResourceLoader;

use MediaWiki\Config\Config;
use MediaWiki\Extension\MetricsPlatform\Services;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;

class Hooks {
	private const XLAB_STREAMS = [
		'product_metrics.web_base',
		'mediawiki.product_metrics.translation_mint_for_readers.experiments',
		'mediawiki.product_metrics.reading_list'
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

			'InstrumentEventIntakeServiceUrl' => $config->get( 'EventLoggingServiceUri' ),

			'streamConfigs' => self::getStreamConfigs(),
			'instrumentConfigs' => self::getStreamConfigsForInstruments(),
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

	/**
	 * Gets the configs for instruments configured in xLab.
	 *
	 * The configs are marshalled into stream-config-like data structures that are compatible with the Metrics Platform
	 * JS and PHP clients.
	 *
	 * @return array
	 */
	private static function getStreamConfigsForInstruments(): array {
		$streamConfigs = MediaWikiServices::getInstance()->getService( 'EventLogging.StreamConfigs' ) ?? [];
		$configs = Services::getConfigsFetcher()->getInstrumentConfigs();

		return array_reduce(
			$configs,
			static function ( array $result, array $config ) use ( $streamConfigs ) {
				$instrumentName = $config['slug'];
				$targetStreamName = $config['stream_name'];

				$result[ $instrumentName ] = [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => $config['contextual_attributes'],
							'stream_name' => $targetStreamName,
						],
					],
					'sample' => $config['sample'],

					// TODO: 'schema_id' => ???
				];

				if ( isset( $streamConfigs[ $targetStreamName ] ) ) {
					$result[ $targetStreamName ] = $streamConfigs[ $targetStreamName ];
				}

				return $result;
			},
			[]
		);
	}
}
