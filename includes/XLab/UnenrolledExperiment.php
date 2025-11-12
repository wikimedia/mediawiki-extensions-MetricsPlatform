<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

/**
 * Represents an experiment for which the current user hasn't been enrolled
 */
class UnenrolledExperiment extends Experiment {

	public function __construct() {
		parent::__construct( null, null, [] );
	}

	/**
	 * @inheritDoc
	 */
	public function send( string $action, ?array $interactionData = null ): void {
	}
}
