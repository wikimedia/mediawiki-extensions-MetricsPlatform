const SCHEMA_ID = '/analytics/product_metrics/web/base/1.4.1';
const STREAM_NAME = 'product_metrics.web_base';

/**
 * This class represents an experiment composed of the
 * name of the experiment and the group which the current user has been
 * assigned to (it will be set to null when either the experiment doesn't
 * exist, the user is not enrolled or MetrisPlatform experimentation
 * capabilities have not been enabled via `MetricsPlatformEnableExperiments`)
 *
 * ```
 * // An experiment can be instantiated via `mw.xLab.getExperiment()`
 * function:
 * const experiment = mw.xLab.getExperiment( 'my_experiment' );
 * // Developers can check if the current user is enrolled in that experiment
 * experiment.isEnrolled();
 * // Developers can get the assigned group
 * experiment.getAssignedGroup();
 * // The experiment can submit a related event
 * experiment.submitInteraction( action );
 * ```
 *
 * @constructor
 * @class Experiment
 *
 * @param {string} name The name of this experiment
 * @param {string} assignedGroup The assigned group for this experiment
 */
function Experiment( name, assignedGroup ) {
	this.name = name;
	this.assignedGroup = assignedGroup;
}

/**
 * Checks whether or not the user is enrolled in this experiment
 *
 * @return {boolean} true is the current user is enrolled in this experiment. false otherwise
 */
Experiment.prototype.isEnrolled = function () {
	return this.assignedGroup !== null;
};

/**
 * Returns the assigned group for this experiment for the current user
 *
 * @return {string} the assigned group for the current user if they are enrolled in this experiment
 * or null if the user isn't
 */
Experiment.prototype.getAssignedGroup = function () {
	return this.assignedGroup;
};

/**
 * Submits an event related to this experiment
 * `instrument_name` and `experiment.enrolled` properties are filled
 * automatically by this function as interactionData. It's the way client
 * libraries can know that the event is related to this experiment
 *
 * @param {string} action The action related to the submitted event
 */
Experiment.prototype.submitInteraction = function ( action ) {
	// If the user is not enrolled in this experiment, it won't be able
	// to send events
	if ( this.assignedGroup === null ) {
		return;
	}

	const interactionData = {
		/* eslint-disable-next-line camelcase */
		instrument_name: this.name,
		// Puts the name of the experiment here for client libraries to know
		// that the event is related to it
		experiment: {
			enrolled: this.name,
			coordinator: 'xLab'
		}
	};

	mw.eventLog.submitInteraction( STREAM_NAME, SCHEMA_ID, action, interactionData );
};

module.exports = Experiment;
