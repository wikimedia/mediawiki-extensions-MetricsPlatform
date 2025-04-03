'use strict';

const c = mw.config.get.bind( mw.config );
const Experiment = require( './Experiment.js' );

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

	if (
		( userExperiments !== null && userExperiments !== undefined ) &&
		( userExperiments.assigned && userExperiments.assigned[ experimentName ] )
	) {
		assignedGroup = userExperiments.assigned[ experimentName ];
	}

	return new Experiment( experimentName, assignedGroup );
}

/**
 * @namespace mw.xLab
 */
mw.xLab = {
	getExperiment
};
