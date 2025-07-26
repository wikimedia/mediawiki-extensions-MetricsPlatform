<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Unit;

use DomainException;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder;
use MediaWiki\Extension\MetricsPlatform\XLab\Experiment;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\MetricsPlatform\MetricsClient;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager
 */
class ExperimentManagerTest extends MediaWikiUnitTestCase {
	private LoggerInterface $logger;
	private MetricsClient $metricsPlatformClient;
	private ExperimentManager $experimentManager;

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock( LoggerInterface::class );
		$this->metricsPlatformClient = $this->createMock( MetricsClient::class );
		$this->experimentManager = new ExperimentManager( $this->logger, $this->metricsPlatformClient );

		$enrollmentResult = new EnrollmentResultBuilder();

		$enrollmentResult->addExperiment( 'main-course', 'overridden', 'mw-user' );
		$enrollmentResult->addAssignment( 'main-course', 'control', true );

		$enrollmentResult->addExperiment(
			'dessert',
			'603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
			'mw-user'
		);
		$enrollmentResult->addAssignment(
			'dessert',
			'control'
		);

		$this->experimentManager->initialize( $enrollmentResult->build() );
	}

	public function testInitializeThrows(): void {
		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( 'ExperimentManager has already been initialized.' );

		$enrollmentResult = new EnrollmentResultBuilder();
		$enrollmentResult->addExperiment( 'my-awesome-experiment', 'asiwyfa', 'mw-user' );
		$enrollmentResult->addAssignment(
			'my-awesome-experiment',
			'treatment'
		);

		$this->experimentManager->initialize( $enrollmentResult->build() );
	}

	public function testGetExperiment(): void {
		$expectedExperiment = new Experiment(
			$this->metricsPlatformClient,
			[
				'enrolled' => 'dessert',
				'assigned' => 'control',
				'subject_id' => '603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
				'sampling_unit' => 'mw-user',
				'coordinator' => 'xLab'
			]
		);
		$actualExperiment = $this->experimentManager->getExperiment( 'dessert' );

		$this->assertEquals( $expectedExperiment, $actualExperiment );
	}

	public function testGetOverriddenExperiment(): void {
		$expectedExperiment = new Experiment(
			$this->metricsPlatformClient,
			[
				'enrolled' => 'main-course',
				'assigned' => 'control',
				'subject_id' => 'overridden',
				'sampling_unit' => 'mw-user',
				'coordinator' => 'forced'
			]
		);
		$actualExperiment = $this->experimentManager->getExperiment( 'main-course' );

		$this->assertEquals( $expectedExperiment, $actualExperiment );
	}

	public function testGetExperimentLogsInformationalMessage(): void {
		$this->logger->expects( $this->once() )
			->method( 'info' )
			->with( 'The foo experiment is not registered. Is the experiment configured and running?' );

		$expectedExperiment = new Experiment( $this->metricsPlatformClient, [] );
		$actualExperiment = $this->experimentManager->getExperiment( 'foo' );

		$this->assertEquals( $expectedExperiment, $actualExperiment );
	}
}
