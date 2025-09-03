<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Unit\XLab;

use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder;
use MediaWiki\Extension\MetricsPlatform\XLab\Experiment;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWiki\Extension\MetricsPlatform\XLab\OverriddenExperiment;
use MediaWiki\Extension\MetricsPlatform\XLab\UnenrolledExperiment;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\MetricsPlatform\MetricsClient;
use Wikimedia\Stats\StatsFactory;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager
 */
class ExperimentManagerTest extends MediaWikiUnitTestCase {
	private LoggerInterface $logger;
	private MetricsClient $metricsPlatformClient;
	private ExperimentManager $experimentManager;
	private StatsFactory $statsFactory;

	public function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock( LoggerInterface::class );
		$this->metricsPlatformClient = $this->createMock( MetricsClient::class );
		$this->statsFactory = StatsFactory::newNull();
		$this->experimentManager = new ExperimentManager(
			$this->logger,
			$this->metricsPlatformClient,
			$this->statsFactory
		);

		$enrollmentResult = new EnrollmentResultBuilder();

		$enrollmentResult->addExperiment( 'main-course', 'overridden', 'overridden' );
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

		$enrollmentResult->addExperiment(
			'active-but-not-enrolled',
			'603c456f34744aac87bf1f086eb46e8f9f0ba7330f5f72c38e3f8031ccd95397',
			'mw-user'
		);

		$this->experimentManager->initialize( $enrollmentResult->build() );
	}

	public function testGetExperiment(): void {
		$expectedExperiment = new Experiment(
			$this->metricsPlatformClient,
			$this->statsFactory,
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
		$expectedExperiment = new OverriddenExperiment(
			$this->logger,
			[
				'enrolled' => 'main-course',
				'assigned' => 'control',
				'subject_id' => 'overridden',
				'sampling_unit' => 'overridden',
				'coordinator' => 'forced'
			]
		);
		$actualExperiment = $this->experimentManager->getExperiment( 'main-course' );

		$this->assertEquals( $expectedExperiment, $actualExperiment );

		$this->assertEquals( 'control', $expectedExperiment->getAssignedGroup() );
		$this->assertTrue( $expectedExperiment->isAssignedGroup( 'control' ) );
	}

	public function testGetExperimentLogsInformationalMessageNonExistingExperiment(): void {
		$this->logger->expects( $this->once() )
			->method( 'info' )
			->with( 'The foo experiment is not registered. Is the experiment configured and running?' );

		$expectedExperiment = new UnenrolledExperiment();
		$actualExperiment = $this->experimentManager->getExperiment( 'foo' );

		$this->assertEquals( $expectedExperiment, $actualExperiment );
	}

	public function testGetExperimentLogsInformationalMessageActiveExperiment(): void {
		$this->logger->expects( $this->once() )
			->method( 'info' )
			->with(
				'The active-but-not-enrolled experiment is not registered. Is the experiment configured and running?'
			);

		$expectedExperiment = new UnenrolledExperiment();
		$actualExperiment = $this->experimentManager->getExperiment( 'active-but-not-enrolled' );

		$this->assertEquals( $expectedExperiment, $actualExperiment );

		$this->assertNull( $expectedExperiment->getAssignedGroup() );
		$this->assertFalse( $expectedExperiment->isAssignedGroup( 'control' ) );
	}

	public function testSendLogsInformationalMessageOverriddenExperiment(): void {
		$this->logger->expects( $this->once() )
			->method( 'info' )
			->with(
				'main-course: The enrolment for this experiment has been overridden. ' .
				'The following event will not be sent'
			);

		$actualExperiment = $this->experimentManager->getExperiment( 'main-course' );
		$actualExperiment->send( 'some-action' );
	}
}
