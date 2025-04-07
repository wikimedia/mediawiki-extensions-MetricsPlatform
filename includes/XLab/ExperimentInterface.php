<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

interface ExperimentInterface {

	/**
	 * Gets the group the current user was assigned by the Experiment Enrollment
	 * Sampling Authority (EESA) when they were enrolled in this experiment.
	 *
	 * @return string|null
	 */
	public function getAssignedGroup(): ?string;

	/**
	 *  Gets whether the assigned group for the current user in this experiment
	 *  is one of the given groups.
	 *
	 * @param string ...$groups
	 * @return bool
	 */
	public function isAssignedGroup( string ...$groups ): bool;

	/**
	 * Sends an interaction event associated with this experiment if the EESA
	 * enrolled the current user in this experiment (for logged-in users only).
	 *
	 * InteractionData can be null in which case the experiment object will
	 * send an event with simply the experiment configuration and action.
	 *
	 * @param string $action
	 * @param array|null $interactionData
	 */
	public function send( string $action, ?array $interactionData ): void;
}
