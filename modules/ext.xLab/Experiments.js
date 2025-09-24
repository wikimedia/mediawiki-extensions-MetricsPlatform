/**
 * A simple experiment-specific instrument that sends a "xLab-loaded" event if the current user is
 * enrolled in the "xlab-mw-module-loaded" experiment and assigned to the treatment group.
 *
 * See https://phabricator.wikimedia.org/T403507 for more context.
 */

const experiment = mw.xLab.getExperiment( 'xlab-mw-module-loaded' );

if ( experiment.isAssignedGroup( 'treatment' ) ) {
	experiment.send(
		'xLab-loaded',
		{
			instrument_name: 'XLabMediaWikiModuleLoaded'
		}
	);
}
