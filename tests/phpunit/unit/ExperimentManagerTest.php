<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\unit;

use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWikiUnitTestCase;
use ReflectionMethod;
use Wikimedia\MetricsPlatform\MetricsClient;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager
 */
class ExperimentManagerTest extends MediaWikiUnitTestCase {

	private ExperimentManager $experimentManager;
	private array $experiments = [
		[
			'slug' => 'dinner',
			'groups' => [
				'control',
				'soap'
			],
			'status' => 1,
			'sample' => [
				'rate' => '0.25'
			]
		]
	];

	public function setUp(): void {
		parent::setUp();
		$mockMetricsClient = $this->createMock( MetricsClient::class );
		$mockCentralIdLookup = $this->createMock( CentralIdLookup::class );

		$this->experimentManager = new ExperimentManager(
			$this->experiments,
			false,
			$mockMetricsClient,
			$mockCentralIdLookup
		);
	}

	public function testParseExperimentEnrollmentsHeader() {
		$parseExperimentEnrollmentsHeader = new ReflectionMethod(
			$this->experimentManager, 'parseExperimentEnrollmentsHeader'
		);
		$parseExperimentEnrollmentsHeader->setAccessible( true );
		$parsedValue = $parseExperimentEnrollmentsHeader->invoke(
			$this->experimentManager, 'experiment_1=group_1;experiment_2=group_2'
		);

		$this->assertEquals(
			[
				'experiment_1' => 'group_1',
				'experiment_2' => 'group_2'
			],
			$parsedValue );
	}

	public function testParseEmptyExperimentEnrollmentsHeader() {
		$parseExperimentEnrollmentsHeader = new ReflectionMethod(
			$this->experimentManager, 'parseExperimentEnrollmentsHeader'
		);
		$parseExperimentEnrollmentsHeader->setAccessible( true );
		$parsedValue = $parseExperimentEnrollmentsHeader->invoke( $this->experimentManager, '' );

		$this->assertEquals( [], $parsedValue );
	}

	public function testParseWrongExperimentEnrollmentsHeader() {
		$parseExperimentEnrollmentsHeader = new ReflectionMethod(
			$this->experimentManager, 'parseExperimentEnrollmentsHeader'
		);
		$parseExperimentEnrollmentsHeader->setAccessible( true );
		$parsedValue = $parseExperimentEnrollmentsHeader->invoke( $this->experimentManager, 'wrong-value' );

		$this->assertEquals( [], $parsedValue );
	}

	public function testParseMalformedExperimentEnrollmentsHeader() {
		$parseExperimentEnrollmentsHeader = new ReflectionMethod(
			$this->experimentManager, 'parseExperimentEnrollmentsHeader'
		);
		$parseExperimentEnrollmentsHeader->setAccessible( true );
		$parsedValue = $parseExperimentEnrollmentsHeader->invoke(
			$this->experimentManager, 'experiment_1=valid_group_1;wrong-value'
		);

		$this->assertEquals( [], $parsedValue );
	}
}
