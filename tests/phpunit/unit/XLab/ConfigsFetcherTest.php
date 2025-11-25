<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Unit\XLab;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\XLab\ConfigsFetcher;
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
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\ConfigsFetcher
 */
class ConfigsFetcherTest extends MediaWikiUnitTestCase {
	private array $instrumentConfigs;
	private \BagOStuff $cache;
	private \BagOStuff $stash;
	private HttpRequestFactory $httpRequestFactory;
	private LoggerInterface $logger;
	private StatsFactory $statsFactory;
	private StatusFormatter $statusFormatter;
	private ConfigsFetcher $fetcher;

	public function setUp(): void {
		parent::setUp();

		$this->instrumentConfigs = self::getMockResponse();
		$this->cache = new HashBagOStuff();
		$this->stash = new HashBagOStuff();
		$this->httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$this->logger = $this->createMock( LoggerInterface::class );
		$this->statsFactory = StatsFactory::newNull();
		$this->statusFormatter = $this->createMock( StatusFormatter::class );
		$this->fetcher = new ConfigsFetcher(
			$this->mockOptions(),
			$this->cache,
			$this->stash,
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

		$expectedValue = $this->instrumentConfigs['responseArray'];
		$expectedKey = $this->stash->makeGlobalKey(
			'MetricsPlatform',
			'instrument',
			1
		);

		$this->assertEquals(
			$expectedValue,
			$this->stash->get( $expectedKey ),
			'The backing store contains the parsed response'
		);
		$this->assertEquals(
			$expectedValue,
			$this->cache->get( $expectedKey ),
			'The cache contains the parsed response'
		);
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

		$expectedKey = $this->stash->makeGlobalKey(
			'MetricsPlatform',
			'instrument',
			1
		);

		$this->assertFalse( $this->stash->get( $expectedKey ) );
		$this->assertFalse( $this->cache->get( $expectedKey ) );

		$this->assertFalse( $status->isGood() );

		if ( $expectedErrorMessages ) {
			$this->assertEquals( $expectedErrorMessages, $status->getMessages( 'error' ) );
		}
	}

	public function testGetConfigsCacheHit(): void {
		$key = $this->stash->makeGlobalKey(
			'MetricsPlatform',
			'instrument',
			1
		);
		$value = array_slice( $this->instrumentConfigs['responseArray'], 0, 2 );

		$this->cache->set( $key, $value );
		$this->stash->delete( $key );

		$expectedValue = $value;
		$expectedValue[0]['sample'] = [
			'unit' => 'pageview',
			'rate' => 1,
		];
		$expectedValue[1]['sample'] = [
			'unit' => 'pageview',
			'rate' => 0.01,
		];

		$this->assertEquals(
			$expectedValue,
			$this->fetcher->getInstrumentConfigs(),
			'If there is a value in the cache, then it is used'
		);
	}

	public function testGetConfigsCacheMissStashHit(): void {
		$key = $this->stash->makeGlobalKey(
			'MetricsPlatform',
			'instrument',
			1
		);
		$value = array_slice( $this->instrumentConfigs['responseArray'], 0, 2 );

		$this->cache->delete( $key );
		$this->stash->set( $key, $value );

		$expectedValue = $value;
		$expectedValue[0]['sample'] = [
			'rate' => 1.0,
			'unit' => 'pageview',
		];
		$expectedValue[1]['sample'] = [
			'rate' => 0.01,
			'unit' => 'pageview',
		];

		$this->assertSame(
			$expectedValue,
			$this->fetcher->getInstrumentConfigs(),
			'If there is a value in the stash, then it is used'
		);

		$this->assertSame(
			$value,
			$this->cache->get( $key ),
			'The cache has been updated with the value fetched from the stash'
		);
	}

	public function testGetConfigsCacheMissStashMiss(): void {
		$key = $this->stash->makeGlobalKey(
			'MetricsPlatform',
			'instrument',
			1
		);

		$this->cache->delete( $key );
		$this->stash->delete( $key );

		$expectedValue = [];

		$this->assertEquals( $expectedValue, $this->fetcher->getInstrumentConfigs()	);

		$this->assertEquals(
			$expectedValue,
			$this->cache->get( $key ),
			'The cache has been updated temporarily'
		);
		$this->assertFalse(
			$this->stash->get( $key ),
			'The stash has not been updated'
		);
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
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$data1 = [
			"name" => "web-scroll-ui",
			"start" => $now->modify( '-1 month' )->format( 'Y-m-d\TH:i:s\Z' ),
			"end" => $now->modify( '+1 month' )->format( 'Y-m-d\TH:i:s\Z' ),
			"sample_unit" => "pageview",
			"sample_rate" => [
				"default" => "1",
				"0.5" => [
					"bnwiki"
				],
			],
			"stream_name" => "mediawiki.web_ui_scroll",
			"schema_title" => "analytics/mediawiki/web_ui_scroll",
			"contextual_attributes" => [
				"page_namespace_id",
				"page_revision_id",
				"page_wikidata_qid",
				"page_is_redirect",
				"page_user_groups_allowed_to_edit",
				"mediawiki_skin"
			],
		];
		$data2 = [
			"name" => "desktop-ui-interactions",
			"start" => $now->modify( '-1 week' )->format( 'Y-m-d\TH:i:s\Z' ),
			"end" => $now->modify( '+1 week' )->format( 'Y-m-d\TH:i:s\Z' ),
			"sample_unit" => "pageview",
			"sample_rate" => [
				"default" => "0.5",
				"0.1" => [
					"frwiki"
				],
				"0.01" => [
					"enwiki"
				]
			],
			"stream_name" => "mediawiki.desktop_ui_interactions",
			"schema_title" => "analytics/product_metrics/mediawiki/desktop_ui_interactions/",
			"contextual_attributes" => [
				"page_id",
				"page_title",
				"page_wikidata_qid",
				"mediawiki_skin"
			],
		];
		// This instrument won't be considered because it has not started yet
		$data3 = [
			"name" => "CTRInstrument",
			"start" => $now->modify( '+1 month' )->format( 'Y-m-d\TH:i:s\Z' ),
			"end" => $now->modify( '-2 month' )->format( 'Y-m-d\TH:i:s\Z' ),
			"sample_unit" => "pageview",
			"sample_rate" => [
				"default" => "0.2",
				"0.1" => [
					"frwiki"
				],
				"0.05" => [
					"enwiki"
				]
			],
			"stream_name" => "product_metrics.web_base",
			"schema_title" => "analytics/product_metrics/web/base/",
			"contextual_attributes" => [
				"page_id",
				"page_title"
			],
		];

		return [
			'responseString' => FormatJson::encode( [ $data1, $data2, $data3 ] ),
			'responseArray' => [ $data1, $data2, $data3 ]
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
