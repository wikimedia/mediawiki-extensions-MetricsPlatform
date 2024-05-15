<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\unit;

use HashBagOStuff;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Json\FormatJson;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use WANObjectCache;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher
 */
class InstrumentConfigsFetcherTest extends MediaWikiUnitTestCase {
	private array $instrumentConfigs;
	private WANObjectCache $WANObjectCache;
	private HttpRequestFactory $httpRequestFactory;
	private LoggerInterface $logger;

	public function setUp(): void {
		parent::setUp();

		$this->instrumentConfigs = $this->getMockResponse();
		$cache = $this->getWANObjectCache();
		$this->setService( 'WANObjectCache', $cache );
		$this->WANObjectCache = $this->getService( 'WANObjectCache' );
		$this->httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$this->logger = $this->createMock( LoggerInterface::class );
	}

	private function getWANObjectCache() {
		return new WANObjectCache( [ 'cache' => new HashBagOStuff() ] );
	}

	public function testSuccess() {
		$this->httpRequestFactory->expects( $this->once() )
			->method( 'get' )
			->willReturn( $this->instrumentConfigs['responseString'] );
		$fetcher = new InstrumentConfigsFetcher(
			$this->mockOptions(),
			$this->WANObjectCache,
			$this->httpRequestFactory,
			$this->logger
		);
		$result = $fetcher->getInstrumentConfigs();
		$this->assertIsArray( $result );
		$this->assertArrayEquals( $this->instrumentConfigs['responseArray'], $result );
	}

	public function testFail() {
		$this->httpRequestFactory->expects( $this->once() )
			->method( 'get' )
			->willReturn( null );
		$fetcher = new InstrumentConfigsFetcher(
			$this->mockOptions(),
			$this->WANObjectCache,
			$this->httpRequestFactory,
			$this->logger
		);
		$result = $fetcher->getInstrumentConfigs();
		$this->assertArrayEquals( [], $result );
	}

	public function testMalformedResponse() {
		$response = $this->instrumentConfigs['responseString'];
		$malformedResponse = str_replace( $response, '"', '\'' );
		$this->httpRequestFactory->expects( $this->once() )
			->method( 'get' )
			->willReturn( $malformedResponse );
		$fetcher = new InstrumentConfigsFetcher(
			$this->mockOptions(),
			$this->WANObjectCache,
			$this->httpRequestFactory,
			$this->logger
		);
		$result = $fetcher->getInstrumentConfigs();
		$this->assertNotEquals( $this->instrumentConfigs['responseArray'], $result );
	}

	private function mockOptions() {
		return new ServiceOptions(
			[ 'MetricsPlatformEnable', 'MetricsPlatformInstrumentConfiguratorBaseUrl' ],
			[
				'MetricsPlatformEnable' => true,
				'MetricsPlatformInstrumentConfiguratorBaseUrl' => 'baseUrl'
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
			"start_date" => "2024-06-01T01:21:55.000Z",
			"end_date" => "2024-06-30T06:00:00.000Z",
			"task" => "T123456",
			"compliance_requirements" => "legal",
			"sample_unit" => "pageview",
			"sample_rate" => 0.5,
			"environments" => "development",
			"security_legal_review" => "pending",
			"status" => "off"
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
			"start_date" => "2024-07-01T01:21:55.000Z",
			"end_date" => "2024-07-31T06:00:00.000Z",
			"task" => "T234567",
			"compliance_requirements" => "legal",
			"sample_unit" => "pageview",
			"sample_rate" => 0.33,
			"environments" => "staging",
			"security_legal_review" => "pending",
			"status" => "on"
		];

		return [
			'responseString' => FormatJson::encode( [ $data1, $data2 ] ),
			'responseArray' => [ $data1, $data2 ]
		];
	}

}
