<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Integration;

use MediaWiki\Extension\EventStreamConfig\Hooks\GetStreamConfigsHook;
use MediaWiki\Extension\MetricsPlatform\Hooks;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\Hooks
 * @group MetricsPlatform
 */
class MetricsPlatformInstrumentConfigsIntegrationTest
	extends MediaWikiIntegrationTestCase
	implements GetStreamConfigsHook
{
	use MockHttpTrait;

	private const STREAM_CONFIGS_FIXTURE = [
		'foobar' => [
			'stream' => 'foobar',
			'schema_title' => 'mediawiki/foobar',
			'sample' => [
				'rate' => 0.5,
				'unit' => 'session',
			],
			'destination_event_service' => 'eventgate-analytics',
		],
		'test.event' => [
			'stream' => 'test.event',
			'schema_title' => 'test/event',
			'sample' => [
				'rate' => 1.0,
				'unit' => 'session',
			],
			'destination_event_service' => 'eventgate-main',
			'topic_prefixes' => [ 'eqiad.', 'codfw.' ],
		],
	];

	public function setUp(): void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgEventStreams' => self::STREAM_CONFIGS_FIXTURE,
			'wgMetricsPlatformEnable' => true,
			'wgMetricsPlatformEnableStreamConfigsMerging' => true,
		] );

		$this->overrideConfigValue( MainConfigNames::DBname, 'bnwiki' );

		$this->installMockHttp( $this->makeFakeHttpRequest( '[
			{
				"id": 1,
				"name": "Web Scroll UI",
				"slug": "web-scroll-ui",
				"description": "abc description",
				"creator": "Jane Doe",
				"owner": "Web Team",
				"purpose": "purpose 1",
				"created_at": "2024-05-03T03:22:15.000Z",
				"updated_at": "2024-05-03T03:22:15.000Z",
				"start_date": "2024-05-03T03:22:15.000Z",
				"end_date": "2024-05-25T06:00:00.000Z",
				"task": "task 1",
				"compliance_requirements": "legal",
				"sample_unit": "pageview",
				"sample_rate": {
					"default": 0.25,
					"0.75": [
						"bnwiki"
					]
				},
				"environments": "development",
				"security_legal_review": "pending",
				"status": 1,
				"stream_name": "mediawiki.web_ui_scroll",
				"schema_title": "analytics/mediawiki/web_ui_scroll",
				"schema_type": "custom",
				"email_address": "web@wikimedia.org",
				"type": "baseline",
				"contextual_attributes": [
					"page_namespace",
					"page_revision_id",
					"page_wikidata_qid",
					"page_is_redirect",
					"page_user_groups_allowed_to_edit",
					"mediawiki_skin"
				]
			},
			{
				"id": 2,
				"name": "Desktop UI Interactions",
				"slug": "desktop-ui-interactions",
				"description": "efd description",
				"creator": "Jill Hill",
				"owner": "Editing Team",
				"purpose": "purpose 1",
				"created_at": "2024-05-03T03:22:15.000Z",
				"updated_at": "2024-05-03T03:22:15.000Z",
				"start_date": "2024-05-03T03:22:15.000Z",
				"end_date": "2024-05-15T06:00:00.000Z",
				"task": "task 1",
				"compliance_requirements": "gdpr",
				"sample_unit": "session",
				"sample_rate": {
					"default": 0.5,
					"0.1": [
						"frwiki",
						"bnwiki",
						"arwiki"
					],
					"0.01": [
						"enwiki"
					]
				},
				"environments": "staging",
				"security_legal_review": "reviewed",
				"status": 1,
				"stream_name": "mediawiki.desktop_ui_interactions",
				"schema_title": "analytics/product_metrics/mediawiki/desktop_ui_interactions/",
				"schema_type": "custom",
				"email_address": "web@wikimedia.org",
				"type": "baseline",
				"contextual_attributes": [
					"page_id",
					"page_title",
					"page_wikidata_qid",
					"mediawiki_skin"
				]
			},
			{
				"id": 3,
				"name": "Test Instrument",
				"slug": "test-instrument",
				"description": "test description",
				"creator": "Test Creator",
				"owner": "Test Team",
				"purpose": "Test Purpose",
				"created_at": "2024-05-03T03:22:15.000Z",
				"updated_at": "2024-05-03T03:22:15.000Z",
				"start_date": "2024-05-03T03:22:15.000Z",
				"end_date": "2024-05-15T06:00:00.000Z",
				"task": "Test task",
				"compliance_requirements": "gdpr",
				"sample_unit": "session",
				"sample_rate": {
					"default": 0.5,
					"0.33": [
						"frwiki"
					]
				},
				"environments": "staging",
				"security_legal_review": "pending",
				"status": 0,
				"stream_name": "test_stream",
				"schema_title": "test_schema",
				"schema_type": "custom",
				"email_address": "info@wikimedia.org",
				"type": "baseline",
				"contextual_attributes": [
					"page_id",
					"page_namespace"
				]
			}
		]'
		) );
		$this->resetServices();
	}

	public function onGetStreamConfigs( array &$streamConfigs ): void {
		$streamConfigs[ 'cat' ] = [
			'stream' => 'cat',
			'schema_title' => 'kitty',
			'sample' => [
				'rate' => 0.5,
				'unit' => 'mouse',
			],
			'destination_event_service' => 'catnip',
		];
		$streamConfigs[ 'dog' ] = [
			'stream' => 'dog',
			'schema_title' => 'canines',
			'sample' => [
				'rate' => 0.75,
				'unit' => 'bone',
			],
			'destination_event_service' => 'ball',
		];
	}

	public function testGetMetricsPlatformInstrumentConfigs(): void {
		$this->setMwGlobals( [
			'wgEventStreams' => self::STREAM_CONFIGS_FIXTURE,
			'wgEventStreamsDefaultSettings' => [
				'topic_prefixes' => [
					'eqiad.'
				],
			],
		] );

		$services = $this->getServiceContainer();

		$hookContainer = $services->getHookContainer();
		$hookContainer->register( 'GetStreamConfigs', [ $this, 'onGetStreamConfigs' ] );

		$mpicStreamConfigs = $services->getService( 'MetricsPlatform.InstrumentConfigs' );
		$streamConfigs = $services->getService( 'EventStreamConfig.StreamConfigs' );

		$firstStream = 'product_metrics.' . str_replace( '-', '_', $mpicStreamConfigs[0]['slug'] );
		$secondStream = 'product_metrics.' . str_replace( '-', '_', $mpicStreamConfigs[1]['slug'] );

		$expected = [
			$firstStream => [
				'stream' => $firstStream,
				'schema_title' => Hooks::PRODUCT_METRICS_WEB_BASE_SCHEMA_TITLE,
				'producers' => [
					'metrics_platform_client' => [
						'provide_values' => [
							"page_namespace",
							"page_revision_id",
							"page_wikidata_qid",
							"page_is_redirect",
							"page_user_groups_allowed_to_edit",
							"mediawiki_skin"
						]
					]
				],
				'sample' => [
					'rate' => 0.75,
					'unit' => $mpicStreamConfigs[0]['sample_unit'],
				],
				'destination_event_service' => 'eventgate-analytics-external',
				'topic_prefixes' => [
					'eqiad.',
				],
				'topics' => [
					"eqiad.$firstStream",
				],
			],
			$secondStream => [
				'stream' => $secondStream,
				'schema_title' => Hooks::PRODUCT_METRICS_WEB_BASE_SCHEMA_TITLE,
				'producers' => [
					'metrics_platform_client' => [
						'provide_values' => [
							"page_id",
							"page_title",
							"page_wikidata_qid",
							"mediawiki_skin"
						]
					]
				],
				'sample' => [
					'rate' => 0.1,
					'unit' => $mpicStreamConfigs[1]['sample_unit'],
				],
				'destination_event_service' => 'eventgate-analytics-external',
				'topic_prefixes' => [
					'eqiad.',
				],
				'topics' => [
					"eqiad.$secondStream",
				],
			],
			'dog' => [
				'stream' => 'dog',
				'schema_title' => 'canines',
				'sample' => [
					'rate' => 0.75,
					'unit' => 'bone',
				],
				'destination_event_service' => 'ball',
				'topic_prefixes' => [
					'eqiad.',
				],
				'topics' => [
					'eqiad.dog',
				],
			],
			'foobar' => [
				'stream' => 'foobar',
				'schema_title' => 'mediawiki/foobar',
				'sample' => [
					'rate' => 0.5,
					'unit' => 'session',
				],
				'destination_event_service' => 'eventgate-analytics',
				'topic_prefixes' => [
					'eqiad.',
				],
				'topics' => [
					'eqiad.foobar',
				],
			],
		];
		$result = $streamConfigs->get( [
			'product_metrics.web_scroll_ui',
			'product_metrics.desktop_ui_interactions',
			'dog',
			'foobar'
		] );
		$this->assertEquals(
			$expected,
			$result,
			'The "foobar" stream from $wgEventStreams, ' .
			'the "dog" stream from onGetStreamConfigs(), ' .
			'the "product_metrics.web_scroll_ui" stream, ' .
			'and the "product_metrics.desktop_ui_interactions" stream are present.'
		);
	}
}
