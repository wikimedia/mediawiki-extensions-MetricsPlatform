<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use Wikimedia\MetricsPlatform\MetricsClient;

class Experiment implements ExperimentInterface {
	private const BASE_STREAM = 'product_metrics.web_base';
	private const BASE_SCHEMAID = '/analytics/product_metrics/web/base/1.4.2';

	/** @var MetricsClient */
	private $metricsClient;

	/** @var array|null */
	private $experimentConfig;

	/**
	 * @param MetricsClient $metricsClient
	 * @param array|null $experimentConfig
	 */
	public function __construct(
		MetricsClient $metricsClient,
		?array $experimentConfig = null
	) {
		$this->metricsClient = $metricsClient;
		$this->experimentConfig = $experimentConfig;
	}

	/**
	 * @inheritDoc
	 */
	public function getAssignedGroup(): ?string {
		return $this->experimentConfig['assigned'] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function isAssignedGroup( ...$groups ): bool {
		return in_array( $this->getAssignedGroup(), $groups, true );
	}

	/**
	 * @inheritDoc
	 */
	public function send( string $action, ?array $interactionData = null ): void {
		// Only submit the event if experiment details exist and are valid.
		if ( $this->isEnrolled() ) {
			$interactionData = array_merge(
				$interactionData ?? [],
				[ 'experiment' => $this->experimentConfig ]
			);
			$this->metricsClient->submitInteraction(
				self::BASE_STREAM,
				self::BASE_SCHEMAID,
				$action,
				$interactionData
			);
		}
	}

	/**
	 * Get the config for the experiment.
	 *
	 * @return array|null
	 */
	public function getExperimentConfig(): ?array {
		return $this->experimentConfig;
	}

	/**
	 * Checks if the user is enrolled in an experiment group.
	 *
	 * @return bool
	 */
	private function isEnrolled(): bool {
		return $this->experimentConfig && $this->getAssignedGroup() !== null;
	}
}
