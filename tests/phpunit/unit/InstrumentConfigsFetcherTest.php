<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\unit;

use Generator;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use MediaWiki\Status\Status;
use MediaWiki\Status\StatusFormatter;
use MediaWikiUnitTestCase;
use MWHttpRequest;
use Psr\Log\LoggerInterface;
use Wikimedia\Message\MessageValue;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher
 */
class InstrumentConfigsFetcherTest extends MediaWikiUnitTestCase {
	private array $instrumentConfigs;
	private \BagOStuff $objectCache;
	private HttpRequestFactory $httpRequestFactory;
	private LoggerInterface $logger;
	private StatsFactory $statsFactory;
	private StatusFormatter $statusFormatter;
	private InstrumentConfigsFetcher $fetcher;

	public function setUp(): void {
		parent::setUp();

		$this->instrumentConfigs = self::getMockResponse();
		$this->objectCache = new HashBagOStuff();
		$this->httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$this->logger = $this->createMock( LoggerInterface::class );
		$this->statsFactory = StatsFactory::newNull();
		$this->statusFormatter = $this->createMock( StatusFormatter::class );
		$this->fetcher = new InstrumentConfigsFetcher(
			$this->mockOptions(),
			$this->objectCache,
			$this->httpRequestFactory,
			$this->logger,
			$this->statsFactory,
			$this->statusFormatter
		);
	}

	public function testSuccess() {
		$httpRequest = $this->getHttpRequest(
			Status::newGood( 200 ), $this->instrumentConfigs['responseString'] );
		$this->httpRequestFactory->expects( $this->once() )
			->method( 'create' )
			->willReturn( $httpRequest );

		$this->fetcher->updateInstrumentConfigs();

		$expected = $this->instrumentConfigs['responseArray'];
		$actual = $this->objectCache->get( $this->objectCache->makeGlobalKey(
			'MetricsPlatform',
			'instrument',
			1
		) );

		$this->assertEquals( $expected, $actual, 'The backing store contains the parsed response' );
	}

	public static function provideRequestFailures(): Generator {
		// Timeout
		yield [
			Status::newFatal( 'http-timed-out', 408, 'Connection timed out' )
				->setResult( false, 408 ),
		];

		// Malformed JSON
		$responseBody = self::getMockResponse()['responseString'];
		$malformedResponseBody = str_replace( $responseBody, '"', '\'' );

		yield [
			Status::newFatal( 'http-timed-out', 400, 'Not Found' )
				->setResult( false, 400 ),
			$malformedResponseBody,
		];

		// Redirect
		yield [
			Status::newGood( 301 ),
			'',
			[
				new MessageValue( 'metricsplatform-xlab-non-successful-response' ),
				new MessageValue( 'metricsplatform-xlab-api-empty-response-body' ),
			],
		];
	}

	/**
	 * @dataProvider provideRequestFailures
	 */
	public function testRequestFailure(
		Status $status,
		string $responseBody = '',
		array $expectedErrorMessages = []
	) {
		$httpRequest = $this->getHttpRequest( $status, $responseBody );

		$this->httpRequestFactory->expects( $this->once() )
			->method( 'create' )
			->willReturn( $httpRequest );

		$this->statusFormatter->expects( $this->once() )
			->method( 'getPsr3MessageAndContext' )
			->willReturn( [ 'error message', [] ] );

		$this->fetcher->updateInstrumentConfigs();

		$result = $this->objectCache->get( $this->objectCache->makeGlobalKey(
			'MetricsPlatform',
			'instrument',
			1
		) );

		$this->assertFalse( $result );

		$this->assertFalse( $status->isGood() );

		if ( $expectedErrorMessages ) {
			$this->assertEquals( $expectedErrorMessages, $status->getMessages( 'error' ) );
		}
	}

	private function mockOptions() {
		return new ServiceOptions(
			[ 'MetricsPlatformInstrumentConfiguratorBaseUrl', 'DBname' ],
			[
				'MetricsPlatformInstrumentConfiguratorBaseUrl' => 'baseUrl',
				'DBname' => 'enwiki',
			] );
	}

	private static function getMockResponse(): array {
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

	private function getHttpRequest( Status $status, $responseBody = '', $headers = [] ) {
		$httpRequest = $this->getMockBuilder( MWHttpRequest::class )
			->disableOriginalConstructor()
			->getMock();
		$httpRequest->method( 'execute' )
			->willReturn( $status );
		$httpRequest->method( 'getResponseHeaders' )
			->willReturn( $headers );
		$httpRequest->method( 'getStatus' )
			->willReturn( $status->getValue() );
		$httpRequest->method( 'getContent' )
			->willReturn( $responseBody );
		return $httpRequest;
	}

}
