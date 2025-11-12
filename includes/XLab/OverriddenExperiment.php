<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use Psr\Log\LoggerInterface;

/**
 * Represents an enrollment experiment that has been overridden for the current user
 */
class OverriddenExperiment extends Experiment {

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly array $experimentConfig
	) {
		parent::__construct( null, null, $this->experimentConfig );
	}

	/**
	 * @inheritDoc
	 */
	public function send( string $action, ?array $interactionData = null ): void {
		if ( $this->experimentConfig ) {
			$experimentName = $this->experimentConfig['enrolled'];

			$this->logger->info(
				$experimentName .
				': The enrolment for this experiment has been overridden. The following event will not be sent',
				[
					'experiment' => $experimentName,
					'action' => $action,
					'interaction_data' => $interactionData,
				]
			);
		}
	}
}
