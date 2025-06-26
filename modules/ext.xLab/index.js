'use strict';

const Experiment = require( './Experiment.js' );

const COOKIE_NAME = 'mpo';

/**
 * @type {Object}
 * @property {boolean} EnableExperimentOverrides
 * @property {string} ExperimentEventIntakeServiceUrl
 * @property {Object|false} streamConfigs
 * @ignore
 */
const config = require( './config.json' );

const { newMetricsClient, DefaultEventSubmitter } = require( 'ext.eventLogging.metricsPlatform' );

const eventSubmitter = new DefaultEventSubmitter(
	config.ExperimentEventIntakeServiceUrl
);
const everyoneExperimentMetricsClient = newMetricsClient( config.streamConfigs, eventSubmitter );

const loggedInExperimentMetricsClient = newMetricsClient( config.streamConfigs, new DefaultEventSubmitter() );

/**
 * Gets an {@link mw.xLab.Experiment} instance that encapsulates the result of enrolling the current
 * user into the experiment. You can use that instance to get which group the user was assigned
 * when they were enrolled into the experiment and send experiment-related analytics events.
 *
 * @example
 * const e = mw.xLab.getExperiment( 'my-awesome-experiment' );
 * const myAwesomeDialog = require( 'my.awesome.dialog' );
 *
 * [
 *   'open',
 *   'default-action',
 *   'primary-action'
 * ].forEach( ( event ) => {
 *   myAwesomeDialog.on( event, () => e.send( event ) );
 * } );
 *
 * // Was the current user assigned to the treatment group?
 * if ( e.isAssignedGroup( 'treatment' ) ) {
 *   myAwesomeDialog.primaryAction.label = 'Awesome!';
 * }
 *
 * @param {string} experimentName The experiment name
 * @return {Experiment}
 * @memberof mw.xLab
 */
function getExperiment( experimentName ) {
	const userExperiments = mw.config.get( 'wgMetricsPlatformUserExperiments' );

	if ( !userExperiments || !userExperiments.assigned[ experimentName ] ) {
		mw.log( 'mw.xLab.getExperiment(): The "' + experimentName + '" experiment isn\'t registered. ' +
			'Is the experiment configured and running?' );
		return new Experiment( everyoneExperimentMetricsClient, experimentName, null, null, null, null );
	}

	const assignedGroup = userExperiments.assigned[ experimentName ];
	const samplingUnit = userExperiments.sampling_units[ experimentName ];
	const subjectId = samplingUnit === 'mw-user' ?
		userExperiments.subject_ids[ experimentName ] :
		'awaiting';
	const coordinator = userExperiments.overrides.includes( experimentName ) ?
		'forced' :
		'xLab';

	/*
	  Provide an alternate MetricsClient for logged-in experiments to override the
	  eventIntakeServiceUrl set by config (wgMetricsPlatformExperimentEventIntakeServiceUrl
	  = '/evt-103e/v2/events?hasty=true' on production) which drops events if everyone experiment
	  enrollments are not included. DefaultEventSubmitter sets DEFAULT_EVENT_INTAKE_URL to the
	  eventgate-analytics-external cluster. See https://phabricator.wikimedia.org/T395779.
	*/
	const metricsClient = samplingUnit === 'mw-user' ?
		loggedInExperimentMetricsClient :
		everyoneExperimentMetricsClient;

	return new Experiment(
		metricsClient,
		experimentName,
		assignedGroup,
		subjectId,
		samplingUnit,
		coordinator
	);
}

function setCookieAndReload( value ) {
	mw.cookie.set( COOKIE_NAME, value );

	// Reloading the window will break the QUnit unit tests. Only do so if we're not in a QUnit
	// testing environment.
	if ( !window.QUnit ) {
		window.location.reload();
	}
}

/**
 * Overrides an experiment enrolment and reloads the page.
 *
 * Note well that this method is only available when `$wgMetricsPlatformEnableExperimentOverrides`
 * is truthy.
 *
 * @param {string} experimentName The name of the experiment
 * @param {string} groupName The assigned group that will override the assigned one
 * @memberof mw.xLab
 */
function overrideExperimentGroup(
	experimentName,
	groupName
) {
	const rawOverrides = mw.cookie.get( COOKIE_NAME, null, '' );
	const part = `${ experimentName }:${ groupName }`;

	if ( rawOverrides === '' ) {
		// If the cookie isn't set, then the value of the cookie is the given override.
		setCookieAndReload( part );
	} else if ( !rawOverrides.includes( `${ experimentName }` ) ) {
		// If the cookie is set but doesn't have an override for the given experiment name/group
		// variant pair, then append the given override.
		setCookieAndReload( `${ rawOverrides };${ part }` );
	} else {
		setCookieAndReload( rawOverrides.replace(
			new RegExp( `${ experimentName }:\\w+?(?=;|$)` ),
			part
		) );
	}
}

/**
 * Clears all enrolment overrides for the experiment and reloads the page.
 *
 * Note well that this method is only available when `$wgMetricsPlatformEnableExperimentOverrides`
 * is truthy.
 *
 * @param {string} experimentName
 * @memberof mw.xLab
 */
function clearExperimentOverride( experimentName ) {
	const rawOverrides = mw.cookie.get( COOKIE_NAME, null, '' );
	const part = null;

	setCookieAndReload( rawOverrides.replace(
		new RegExp( `${ experimentName }:\\w+?(?=;|$)` ),
		part
	) );
}

/**
 * Clears all experiment enrolment overrides for all experiments and reloads the page.
 *
 * Note well that this method is only available when `$wgMetricsPlatformEnableExperimentOverrides`
 * is truthy.
 *
 * @memberof mw.xLab
 */
function clearExperimentOverrides() {
	setCookieAndReload( null );
}

/**
 * @namespace mw.xLab
 */
mw.xLab = {
	getExperiment
};

// JS overriding experimentation feature
if ( config.EnableExperimentOverrides || window.QUnit ) {
	mw.xLab.overrideExperimentGroup = overrideExperimentGroup;
	mw.xLab.clearExperimentOverride = clearExperimentOverride;
	mw.xLab.clearExperimentOverrides = clearExperimentOverrides;
}

if ( window.QUnit ) {
	mw.xLab.Experiment = Experiment;
}
