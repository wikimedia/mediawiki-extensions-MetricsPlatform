const SCHEMA_ID = '/analytics/product_metrics/web/base/1.4.2';
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
 * @param {string} subjectId The subject id for this experiment
 * @param {string} samplingUnit The sampling unit for this experiment
 * @param {string} coordinator The coordinator that has set the enrollment for this experiment: `xLab`
 * if the enrollment is not overriden and `forced` in the case it's
 */
function Experiment( name, assignedGroup, subjectId, samplingUnit, coordinator ) {
	this.name = name;
	this.assignedGroup = assignedGroup;
	this.subjectId = subjectId;
	this.samplingUnit = samplingUnit;
	this.coordinator = coordinator;
}

/**
 * Checks whether or not the user is enrolled in this experiment
 *
 * @ignore
 * @return {boolean} true is the current user is enrolled in this experiment. false otherwise
 */
function isEnrolled() {
	return this.assignedGroup !== null;
}

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
 * The entire `experiment` fragment will be filled automatically
 * and, optionally, additional data can be added as interaction data
 *
 * @param {string} action The action related to the submitted event
 * @param {Object} interactionData Additional data
 */
Experiment.prototype.send = function ( action, interactionData ) {
	// If the user is not enrolled in this experiment, it won't be able
	// to send events
	if ( !isEnrolled.call( this ) ) {
		return;
	}

	const enrollmentDetails = {
		// Fills all the details related to the experiment enrollment
		experiment: {
			enrolled: this.name,
			assigned: this.assignedGroup,

			subject_id: this.subjectId,

			sampling_unit: this.samplingUnit,
			coordinator: this.coordinator
		}
	};
	interactionData = Object.assign( {}, interactionData, enrollmentDetails );

	mw.eventLog.submitInteraction( STREAM_NAME, SCHEMA_ID, action, interactionData );
};

/**
 * Gets whether the assigned group for the current user in this experiment is one of the given
 * groups.
 *
 * @param {...any} groups
 * @example
 * const e = mw.xLab.getExperiment( 'my-awesome-experiment-1' );
 *
 * // Is the current user assigned A or B for the My Awesome Experiment 1 experiment?
 * if ( e.isAssignedGroup( 'A', 'B' ) {
 *   // ...
 * }
 *
 * @return {boolean}
 */
Experiment.prototype.isAssignedGroup = function ( ...groups ) {
	return groups.indexOf( this.assignedGroup ) !== -1;
};

module.exports = Experiment;
