/**
 * A simple experiment-specific instrument that sends a "xLab-loaded" event if the current user is
 * enrolled in the "xlab-mw-module-loaded" experiment.
 *
 * See https://phabricator.wikimedia.org/T403507 for more context.
 */

mw.loader.using( 'ext.xLab' ).then( () => {
	const experiment = mw.xLab.getExperiment( 'xlab-mw-module-loaded' );

	experiment.send(
		'xLab-loaded',
		{
			instrument_name: 'XLabMediaWikiModuleLoaded'
		}
	);
} );
