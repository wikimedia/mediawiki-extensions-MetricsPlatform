<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Integration\XLab\ResourceLoader;

use Generator;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Extension\MetricsPlatform\XLab\ResourceLoader\Hooks;
use MediaWiki\ResourceLoader as RL;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\ResourceLoader\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {
	private InstrumentConfigsFetcher $configsFetcher;
	private RL\Context $context;
	private Config $config;

	public function setUp(): void {
		$this->configsFetcher = $this->createMock( InstrumentConfigsFetcher::class );

		$this->setService( 'MetricsPlatform.ConfigsFetcher', $this->configsFetcher );

		$this->context = RL\Context::newDummyContext();
		$this->config = new HashConfig( [
			'MetricsPlatformEnableExperimentOverrides' => false,
			'MetricsPlatformExperimentEventIntakeServiceUrl' => 'http://foo.bar',
			'EventLoggingServiceUri' => 'http://baz.qux',
		] );
	}

	public function testGetConfigForXLabModule(): void {
		$configForXLabModule = Hooks::getConfigForXLabModule( $this->context, $this->config );

		$this->assertArrayContains(
			[
				'EnableExperimentOverrides' => false,
				'EveryoneExperimentEventIntakeServiceUrl' => 'http://foo.bar',
				'LoggedInExperimentEventIntakeServiceUrl' => 'http://baz.qux',
				'InstrumentEventIntakeServiceUrl' => 'http://baz.qux',
			],
			$configForXLabModule
		);
	}

	public static function provideInstrumentConfigs(): Generator {
		yield [ false, [], [] ];
		yield [
			false,
			[
				[
					'slug' => 'foo',
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
					'stream_name' => 'product_metrics.web_base.foo',
					'contextual_attributes' => [
						'page_namespace',
						'mediawiki_skin',
					],
				],
				[
					'slug' => 'bar',
					'sample' => [
						'unit' => 'session',
						'rate' => 0.5,
					],
					'stream_name' => 'product_metrics.web_base.bar',
					'contextual_attributes' => [
						'mediawiki_database',
						'performer_is_bot',
					],
				],
			],
			[
				'foo' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'page_namespace',
								'mediawiki_skin',
							],
							'stream_name' => 'product_metrics.web_base.foo',
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
				],
				'bar' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'mediawiki_database',
								'performer_is_bot',
							],
							'stream_name' => 'product_metrics.web_base.bar',
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 0.5,
					],
				],
			],
		];

		// Configs for streams referenced in instrumentConfig.producers.metrics_platform_client.stream_name are copied.
		yield [
			[
				'product_metrics.web_base' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'page_namespace',
								'mediawiki_skin',
							],
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
				],
			],
			[
				[
					'slug' => 'foo',
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
					'stream_name' => 'product_metrics.web_base',
					'contextual_attributes' => [
						'page_namespace',
						'mediawiki_skin',
					],
				],
			],
			[
				'foo' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'page_namespace',
								'mediawiki_skin',
							],
							'stream_name' => 'product_metrics.web_base',
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
				],
				'product_metrics.web_base' => [
					'producers' => [
						'metrics_platform_client' => [
							'provide_values' => [
								'page_namespace',
								'mediawiki_skin',
							],
						],
					],
					'sample' => [
						'unit' => 'session',
						'rate' => 1,
					],
				],
			]
		];
	}

	/**
	 * @dataProvider provideInstrumentConfigs
	 */
	public function testGetStreamConfigsForInstruments(
		$streamConfigs,
		array $instrumentConfigs,
		array $expectedStreamConfigs
	): void {
		$this->setService( 'EventLogging.StreamConfigs', $streamConfigs );

		$this->configsFetcher->expects( $this->once() )
			->method( 'getInstrumentConfigs' )
			->willReturn( $instrumentConfigs );

		$configForXLabModule = Hooks::getConfigForXLabModule( $this->context, $this->config );
		$actualStreamConfigs = $configForXLabModule[ 'instrumentConfigs' ];

		$this->assertEquals( $expectedStreamConfigs, $actualStreamConfigs );
	}
}
