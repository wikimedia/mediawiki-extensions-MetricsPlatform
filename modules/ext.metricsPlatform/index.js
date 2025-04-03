const COOKIE_NAME = 'mpo';

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
 * @param {string} experimentName
 * @param {string} featureVariantName
 * @param {string} featureVariantValue
 * @memberOf mw.metricsPlatform
 */
function overrideExperimentEnrollment(
	experimentName,
	featureVariantName,
	featureVariantValue
) {
	const rawOverrides = mw.cookie.get( COOKIE_NAME, null, '' );
	const part = `${ experimentName }:${ featureVariantName }:${ featureVariantValue }`;

	if ( rawOverrides === '' ) {
		// If the cookie isn't set, then the value of the cookie is the given override.

		setCookieAndReload( part );
	} else if ( rawOverrides.indexOf( `${ experimentName }:${ featureVariantName }` ) === -1 ) {
		// If the cookie is set but doesn't have an override for the given experiment name/feature
		// variant pair, then append the given override.

		setCookieAndReload( `${ rawOverrides };${ part }` );
	} else {
		setCookieAndReload( rawOverrides.replace(
			new RegExp( `${ experimentName }:${ featureVariantName }:\\w+?(?=;|$)` ),
			part
		) );
	}
}

/**
 * Clears all experiment enrollment overrides and reloads the current URL.
 *
 * @memberOf mw.metricsPlatform
 */
function clearExperimentEnrollmentOverrides() {
	setCookieAndReload( null );
}

/**
 * @namespace mw.metricsPlatform
 */
mw.metricsPlatform = {};

if ( config.MetricsPlatformEnableExperimentOverrides || window.QUnit ) {
	mw.metricsPlatform.overrideExperimentEnrollment = overrideExperimentEnrollment;
	mw.metricsPlatform.clearExperimentEnrollmentOverrides = clearExperimentEnrollmentOverrides;
}
