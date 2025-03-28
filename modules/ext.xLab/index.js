'use strict';

const c = mw.config.get.bind( mw.config );
const Experiment = require( './Experiment.js' );

/**
 * @typedef {Object} Config
 * @property {boolean} MetricsPlatformEnableExperiments
 */

/** @type {Config} */
const config = require( './config.json' );

/**
 * Description
 *
 * @param {string} experimentName The experiment name
 * @return {Experiment} The experiment whose name has been passed as parameter
 */
function getExperiment( experimentName ) {
	const userExperiments = c( 'wgMetricsPlatformUserExperiments' );
	let assignedGroup = null;

	if (
		( userExperiments !== null && userExperiments !== undefined ) &&
		( userExperiments.assigned && userExperiments.assigned[ experimentName ] )
	) {
		assignedGroup = userExperiments.assigned[ experimentName ];
	}

	return new Experiment( experimentName, assignedGroup );
}

mw.xLab = {};

// JS xLab API
if ( config.MetricsPlatformEnableExperiments || window.QUnit ) {
	mw.xLab.getExperiment = getExperiment;
}
