const SCHEMA_ID = '/analytics/product_metrics/web/base/1.4.2';
const STREAM_NAME = 'product_metrics.web_base';

/**
 * @class
 * @classdesc This class represents an experiment enrolment for the current user. You can use it to
 *  get which group the user was assigned when they were enrolled into the experiment and send
 *  experiment-related analytics events.
 *
 *  Note well that this class should be constructed using `mw.xLab.getExperiment()` instead, e.g.
 *
 *  ```
 *  const experiment = mw.xLab.getExperiment( 'my-awesome-experiment' );
 *  ```
 * @hideconstructor
 *
 * @package
 *
 * @param {Object} metricsClient
 * @param {string} name
 * @param {string|null} assignedGroup
 * @param {string} [subjectId] The subject ID for this experiment
 * @param {string} [samplingUnit] The sampling unit for this experiment
 * @param {string} [coordinator] The name of the system that coordinated the enrollment of the user
 *  into the experiment. This parameter is used as the value for the `experiment.coordinator` field
 *  on all analytics events sent via {@link Experiment#send} so it should be one of `xLab`,
 *  `custom`, or `forced`
 */
function Experiment(
	metricsClient,
	name,
	assignedGroup,
	subjectId,
	samplingUnit,
	coordinator
) {
	this.metricsClient = metricsClient;
	this.name = name;
	this.assignedGroup = assignedGroup;
	this.subjectId = subjectId;
	this.samplingUnit = samplingUnit;
	this.coordinator = coordinator;
	this.streamName = STREAM_NAME;
	this.schemaID = SCHEMA_ID;

}

/**
 * Checks whether the current user is enrolled in the experiment.
 *
 * @ignore
 * @return {boolean}
 */
function isEnrolled() {
	return this.assignedGroup !== null;
}

/**
 * Gets the group assigned to the current user.
 *
 * @return {string|null}
 */
Experiment.prototype.getAssignedGroup = function () {
	return this.assignedGroup;
};

/**
 * Sends an analytics event related to the experiment.
 *
 * If the user is enrolled in the experiment, then the event is decorated with experiment-related
 * data and sent. The experiment-related data are specified and documented in
 * [the `fragment/analytics/product_metrics/experiment` schema fragment][0].
 *
 * By default, the analytics event will be sent to the `product_metrics.web_base` stream and be
 * validated with the `/analytics/product_metrics/web/base/1.4.2` schema. The stream and schema
 * can be overridden with {@link Experiment#setStream} and {@link Experiment#setSchema},
 * respectively.
 *
 * [0]: https://gitlab.wikimedia.org/repos/data-engineering/schemas-event-secondary/-/blob/master/jsonschema/fragment/analytics/product_metrics/experiment/current.yaml?ref_type=heads
 *
 * @see mw.eventLog.submitInteraction
 *
 * @param {string} action The action that the user enrolled in this experiment took, e.g. "hover",
 *  "click"
 * @param {Object} [interactionData] Additional data about the action that the user enrolled in the
 *  experiment took
 */
Experiment.prototype.send = function ( action, interactionData ) {
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

	this.metricsClient.submitInteraction( this.streamName, this.schemaID, action, interactionData );
};

/**
 * Submits an event related to this experiment.
 *
 * This method makes `Experiment` compatible with [the click-through rate implementation in the
 * `ext.wikimediaEvents.xLab` ResourceLoader module][0] by proxying to {@link Experiment#send}.
 * Calling this outside of xLab is not supported.
 *
 * [0]: https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/extensions/WikimediaEvents/+/master/modules/ext.wikimediaEvents.xLab/ClickThroughRateInstrument.js
 *
 * @see https://phabricator.wikimedia.org/T394675
 *
 * @package
 *
 * @param {string} action The action related to the submitted event
 * @param {Object} interactionData Additional data
 */
Experiment.prototype.submitInteraction = function ( action, interactionData ) {
	this.send( action, interactionData );
};

/**
 * Gets whether the group assigned to the current user is one of the given groups.
 *
 * @see Experiment#getAssignedGroup
 *
 * @example
 * const e = mw.xLab.getExperiment( 'my-awesome-experiment' );
 *
 * // Is the current user assigned A or B for the "My Awesome Experiment" experiment?
 * if ( e.isAssignedGroup( 'A', 'B' ) {
 *   // ...
 * }
 *
 * @param {...string} groups
 * @return {boolean}
 */
Experiment.prototype.isAssignedGroup = function ( ...groups ) {
	return groups.includes( this.assignedGroup );
};

/**
 * Sets the stream to send analytics events to with {@link Experiment#send}.
 *
 * This method is chainable.
 *
 * @param {string} streamName
 * @return {Experiment} The instance on which this method was called
 */
Experiment.prototype.setStream = function ( streamName ) {
	this.streamName = streamName;

	return this;
};

/**
 * Sets the ID of the schema used to validate analytics events sent with {@link Experiment#send}.
 *
 * This method is chainable.
 *
 * @param {string} schemaID
 * @return {Experiment}
 */
Experiment.prototype.setSchema = function ( schemaID ) {
	this.schemaID = schemaID;

	return this;
};

module.exports = Experiment;
