const SCHEMA_ID = '/analytics/product_metrics/web/base/1.4.0';
const STREAM_NAME = 'product_metrics.web_base';

/**
 * @constructor
 * @class Experiment
 * @classdesc An experiment
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
 *
 * @param {string} action The action related to the submitted event
 */
Experiment.prototype.submitInteraction = function ( action ) {
	const interactionData = {
		/* eslint-disable-next-line camelcase */
		instrument_name: this.name,
		// Puts the name of the experiment here for client libraries to know
		// that the event is related to it
		experiment: {
			enrolled: this.name
		}
	};

	mw.eventLog.submitInteraction( STREAM_NAME, SCHEMA_ID, action, interactionData );
};

module.exports = Experiment;
