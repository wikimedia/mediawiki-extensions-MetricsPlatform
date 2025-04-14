'use strict';

const COOKIE_NAME = 'mpo';
const c = mw.config.get.bind( mw.config );
const Experiment = require( './Experiment.js' );

/**
 * @typedef {Object} Config
 * @property {boolean} MetricsPlatformEnableExperimentOverrides
 * @ignore
 */

/**
 * @type {Config}
 * @ignore
*/
const config = require( './config.json' );

/**
 * Gets the experiment enrollment details for the experiment whose name is passed
 * as a parameter
 * This experiment enrollment will contain the name of the experiment and the group
 * the user has been assigned to (this value will be null in the case either the
 * experiment doesn't exist or the user is not in sampled for that experiment)
 * The assigned group will be also `null` when `MetricsPlatformEnableExperiments`
 * is falsy
 *
 * @param {string} experimentName The experiment name
 * @return {Experiment} The experiment enrollment details for the experiment whose
 * name has been passed as a parameter
 * @memberOf mw.xLab
 */
function getExperiment( experimentName ) {
	const userExperiments = c( 'wgMetricsPlatformUserExperiments' );
	let assignedGroup = null;
	let subjectId;
	let samplingUnit;
	let coordinator;

	if (
		userExperiments &&
		userExperiments.assigned[ experimentName ]
	) {
		assignedGroup = userExperiments.assigned[ experimentName ];
		/* eslint-disable-next-line camelcase */
		subjectId = userExperiments.subject_ids[ experimentName ];
		/* eslint-disable-next-line camelcase */
		samplingUnit = userExperiments.sampling_units[ experimentName ];
		coordinator = userExperiments.overrides.indexOf( experimentName ) !== -1 ? 'forced' : 'xLab';
	}

	// TODO Add an informational message in the case the experiment doesn't exist

	return new Experiment( experimentName, assignedGroup, subjectId, samplingUnit, coordinator );
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
 * Overrides an experiment enrollment and reloads the current URL.
 *
 * @param {string} experimentName The name of the experiment
 * @param {string} groupName The assigned group that will override the assigned one
 * @memberOf mw.xLab
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
	} else if ( rawOverrides.indexOf( `${ experimentName }` ) === -1 ) {
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
 * Clears enrollment overrides for a specific experiment and reloads the current URL.
 *
 * @param {string} experimentName Name of the experiment whose enrollment will be cleared
 * @memberOf mw.xLab
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
 * Clears all experiment enrollment overrides and reloads the current URL.
 *
 * @memberOf mw.xLab
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
if ( config.MetricsPlatformEnableExperimentOverrides || window.QUnit ) {
	mw.xLab.overrideExperimentGroup = overrideExperimentGroup;
	mw.xLab.clearExperimentOverride = clearExperimentOverride;
	mw.xLab.clearExperimentOverrides = clearExperimentOverrides;
}

if ( window.QUnit ) {
	mw.xLab.Experiment = Experiment;
}
