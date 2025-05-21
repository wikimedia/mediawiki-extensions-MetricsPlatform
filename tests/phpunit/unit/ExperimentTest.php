<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\unit;

use MediaWiki\Extension\MetricsPlatform\XLab\Experiment;
use MediaWikiUnitTestCase;
use Wikimedia\MetricsPlatform\MetricsClient;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Experiment
 */
class ExperimentTest extends MediaWikiUnitTestCase {
	private MetricsClient $mockMetricsClient;

	/** @var array */
	private $experimentConfig = [
		'enrolled' => "test_experiment",
		'assigned' => "treatment",
		'subject_id' => "asdfqwerty",
		'sampling_unit' => "mw-user",
		'other_assigned' => [ "another_experiment", "yet_another_experiment" ],
		'coordinator' => "xLab"
	];

	/** @var Experiment */
	private $experiment;

	/** @var string */
	private $streamName = 'product_metrics.web_base';

	/** @var string */
	private $schemaId = '/analytics/product_metrics/web/base/1.4.2';

	/** @var string */
	private $action = 'test_action';

	/** @var array */
	private $interactionData = [
		'action_source' => 'test_action_source',
		'action_context' => 'test_action_context',
	];

	public function setUp(): void {
		parent::setUp();
		$this->mockMetricsClient = $this->createMock( MetricsClient::class );
		$this->experiment = new Experiment(
			$this->mockMetricsClient,
			$this->experimentConfig
		);
	}

	public function testGetAssignedGroupWithExperimentConfig() {
		$group = $this->experiment->getAssignedGroup();
		$this->assertEquals( 'treatment', $group );
	}

	public function testGetAssignedGroupWithNoExperimentConfig() {
		$experiment = new Experiment(
			$this->mockMetricsClient,
			[]
		);
		$group = $experiment->getAssignedGroup();
		$this->assertNull( $group );
	}

	public function testIsAssignedGroupInGroup() {
		$this->assertTrue( $this->experiment->isAssignedGroup( 'treatment', 'group_a', 'group_b' ) );
	}

	public function testIsAssignedGroupNotInGroup() {
		$this->assertFalse( $this->experiment->isAssignedGroup( 'group_a', 'group_b', 'group_c' ) );
	}

	public function testSendArgumentsDefault() {
		$this->mockMetricsClient
			->expects( $this->once() )
			->method( 'submitInteraction' )
			->willReturnCallback( function ( $streamName, $schemaId, $action, $interactionData ): bool {
				$this->assertSame( [
					$this->streamName,
					$this->schemaId,
					$this->action,
					array_merge( $this->interactionData, [ 'experiment' => $this->experimentConfig ] )
				],
				[ $streamName, $schemaId, $action, $interactionData ]
				);
				return true;
			} );

		$this->experiment->send( $this->action, $this->interactionData );
	}

	public function testSendArgumentsNoInteractionData() {
		$this->mockMetricsClient
			->expects( $this->once() )
			->method( 'submitInteraction' )
			->willReturnCallback( function ( $streamName, $schemaId, $action, $experimentConfig ): bool {
				$this->assertSame( [
					$this->streamName,
					$this->schemaId,
					$this->action,
					[ 'experiment' => $this->experimentConfig ]
				],
					[ $streamName, $schemaId, $action, $experimentConfig ]
				);
				return true;
			} );

		$this->experiment->send( $this->action );
	}

	public function testSendArgumentsWithMissingExperimentConfig() {
		$experiment = new Experiment( $this->mockMetricsClient );

		$this->mockMetricsClient
			->expects( $this->never() )
			->method( 'submitInteraction' )
			->willReturnCallback( function ( $action, $interactionData ): bool {
				$this->assertSame( [
					$this->action,
					array_merge( $this->interactionData, [ 'experiment' => $this->experimentConfig ] )
				],
					[ $action, $interactionData ]
				);
				return true;
			} );

		$experiment->send( $this->action, $this->interactionData );
		$this->assertNull( $experiment->getExperimentConfig() );
	}
}
