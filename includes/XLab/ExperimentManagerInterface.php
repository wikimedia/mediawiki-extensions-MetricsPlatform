<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

interface ExperimentManagerInterface {
	/**
	 * Get the current user's experiment object.
	 *
	 * @param string $experimentName
	 * @return Experiment
	 */
	public function getExperiment( string $experimentName ): Experiment;

}
