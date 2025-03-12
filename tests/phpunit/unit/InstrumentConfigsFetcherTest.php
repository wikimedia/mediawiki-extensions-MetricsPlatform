<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use MediaWikiUnitTestCase;
use MWHttpRequest;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher
 */
class InstrumentConfigsFetcherTest extends MediaWikiUnitTestCase {
	private array $instrumentConfigs;
	private WANObjectCache $WANObjectCache;
	private HttpRequestFactory $httpRequestFactory;
	private LoggerInterface $logger;
	private StatsFactory $statsFactory;
	private StatusFormatter $statusFormatter;

	public function setUp(): void {
		parent::setUp();

		$this->instrumentConfigs = $this->getMockResponse();
		$cache = $this->getWANObjectCache();
		$this->setService( 'WANObjectCache', $cache );
		$this->WANObjectCache = $this->getService( 'WANObjectCache' );
		$this->httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$this->logger = $this->createMock( LoggerInterface::class );
		$this->statsFactory = StatsFactory::newNull();
		$this->statusFormatter = $this->createMock( StatusFormatter::class );
	}

	private function getWANObjectCache() {
		return new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	public function testSuccess() {
		$httpRequest = $this->getHttpRequest(
			StatusValue::newGood( 200 ), 200, $this->instrumentConfigs['responseString'] );
		$this->httpRequestFactory->expects( $this->once() )
			->method( 'create' )
			->willReturn( $httpRequest );

		$fetcher = new InstrumentConfigsFetcher(
			$this->mockOptions(),
			$this->WANObjectCache,
			$this->httpRequestFactory,
			$this->logger,
			$this->statsFactory,
			$this->statusFormatter
		);
		$result = $fetcher->getInstrumentConfigs();
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result, 'It should filter out disabled instruments' );

		$expectedConfig = $this->instrumentConfigs['responseArray'][1];
		$expectedConfig['sample'] = [
			'rate' => 0.01,
			'unit' => 'pageview',
		];
		$expectedResult = [ $expectedConfig ];

		$this->assertArrayEquals( $expectedResult, $result );
	}

	public function testFailTimeout() {
		$status = StatusValue::newFatal( "http-timed-out", 408, 'Connection timed out' );
		$status->setResult( false, 408 );
		$httpRequest = $this->getHttpRequest( $status, 408, "" );
		$this->httpRequestFactory->expects( $this->once() )
			->method( 'create' )
			->willReturn( $httpRequest );

		$this->statusFormatter->expects( $this->once() )
			->method( 'getPsr3MessageAndContext' )
			->willReturn( [ 'error message', [] ] );

		$fetcher = new InstrumentConfigsFetcher(
			$this->mockOptions(),
			$this->WANObjectCache,
			$this->httpRequestFactory,
			$this->logger,
			$this->statsFactory,
			$this->statusFormatter
		);
		$result = $fetcher->getInstrumentConfigs();
		$this->assertArrayEquals( [], $result );
	}

	public function testMalformedResponse() {
		$response = $this->instrumentConfigs['responseString'];
		$malformedResponse = str_replace( $response, '"', '\'' );
		$status = StatusValue::newFatal( "http-timed-out", 400, 'Not Found' );
		$status->setResult( false, 400 );
		$httpRequest = $this->getHttpRequest( $status, 400, $malformedResponse );
		$this->httpRequestFactory->expects( $this->once() )
			->method( 'create' )
			->willReturn( $httpRequest );

		$this->statusFormatter->expects( $this->once() )
			->method( 'getPsr3MessageAndContext' )
			->willReturn( [ 'message', [] ] );

		$fetcher = new InstrumentConfigsFetcher(
			$this->mockOptions(),
			$this->WANObjectCache,
			$this->httpRequestFactory,
			$this->logger,
			$this->statsFactory,
			$this->statusFormatter
		);
		$result = $fetcher->getInstrumentConfigs();
		$this->assertNotEquals( $this->instrumentConfigs['responseArray'], $result );
	}

	private function mockOptions() {
		return new ServiceOptions(
			[ 'MetricsPlatformInstrumentConfiguratorBaseUrl', 'DBname' ],
			[
				'MetricsPlatformInstrumentConfiguratorBaseUrl' => 'baseUrl',
				'DBname' => 'enwiki',
			] );
	}

	private function getMockResponse(): array {
		$data1 = [
			"id" => 1,
			"name" => "Web Scroll UI",
			"slug" => "web-scroll-ui",
			"description" => "Tracks scroll events",
			"creator" => "Jane Doe",
			"owner" => "Web Team",
			"purpose" => "KR 3.5",
			"created_at" => "2024-05-29T01:21:55.000Z",
			"updated_at" => "2024-05-30T01:21:55.000Z",
			"utc_start_dt" => "2024-06-01T01:21:55.000Z",
			"utc_end_dt" => "2024-06-30T06:00:00.000Z",
			"task" => "T123456",
			"compliance_requirements" => "legal",
			"sample_unit" => "pageview",
			"sample_rate" => [
				"default" => 1,
				"0.5" => [
					"bnwiki"
				],
			],
			"environments" => "development",
			"security_legal_review" => "pending",
			"status" => 0,
			"stream_name" => "mediawiki.web_ui_scroll",
			"schema_title" => "analytics/mediawiki/web_ui_scroll",
			"schema_type" => "custom",
			"email_address" => "web@wikimedia.org",
			"type" => "baseline",
			"contextual_attributes" => [
				"page_namespace",
				"page_revision_id",
				"page_wikidata_qid",
				"page_is_redirect",
				"page_user_groups_allowed_to_edit",
				"mediawiki_skin"
			],
		];
		$data2 = [
			"id" => 2,
			"name" => "Desktop UI Interactions",
			"slug" => "desktop-ui-interactions",
			"description" => "Track UI events in desktop",
			"creator" => "James Doe",
			"owner" => "Web Team",
			"purpose" => "KR 3.6",
			"created_at" => "2024-06-01T01:21:55.000Z",
			"updated_at" => "2024-06-03T01:21:55.000Z",
			"utc_start_dt" => "2024-07-01T01:21:55.000Z",
			"utc_end_dt" => "2024-07-31T06:00:00.000Z",
			"task" => "T234567",
			"compliance_requirements" => "legal",
			"sample_unit" => "pageview",
			"sample_rate" => [
				"default" => 0.5,
				"0.1" => [
					"frwiki"
				],
				"0.01" => [
					"enwiki"
				]
			],
			"environments" => "staging",
			"security_legal_review" => "pending",
			"status" => 1,
			"stream_name" => "mediawiki.desktop_ui_interactions",
			"schema_title" => "analytics/product_metrics/mediawiki/desktop_ui_interactions/",
			"schema_type" => "custom",
			"email_address" => "web@wikimedia.org",
			"type" => "baseline",
			"contextual_attributes" => [
				"page_id",
				"page_title",
				"page_wikidata_qid",
				"mediawiki_skin"
			],
		];

		return [
			'responseString' => FormatJson::encode( [ $data1, $data2 ] ),
			'responseArray' => [ $data1, $data2 ]
		];
	}

	private function getHttpRequest( $statusValue, $statusCode, $content, $headers = [] ) {
		$httpRequest = $this->getMockBuilder( MWHttpRequest::class )
			->disableOriginalConstructor()
			->getMock();
		$httpRequest->method( 'execute' )
			->willReturn( Status::wrap( $statusValue ) );
		$httpRequest->method( 'getResponseHeaders' )
			->willReturn( $headers );
		$httpRequest->method( 'getStatus' )
			->willReturn( $statusCode );
		$httpRequest->method( 'getContent' )
			->willReturn( $content );
		return $httpRequest;
	}

}
