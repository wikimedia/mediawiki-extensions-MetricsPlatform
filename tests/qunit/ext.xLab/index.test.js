// Test cases when the user is not logged-in (wgMetricsPlatformUserExperiments will be 'undefined')
QUnit.module( 'ext.xLab/Experiment - User is not logged-in', {
	beforeEach: function () {
		mw.config.set( 'wgMetricsPlatformUserExperiments', undefined );
	}
} );

QUnit.test( 'getExperiment() - The user is not enrolled in this experiment', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'an_experiment_name' );

	assert.strictEqual( experiment.isEnrolled(), false );
	assert.strictEqual( experiment.getAssignedGroup(), null );
} );

// Test cases when the user is logged-in and there are no experiments
// (wgMetricsPlatformUserExperiments will be an empty array')
QUnit.module( 'ext.xLab/Experiment - User is logged-in and there are no experiments', {
	beforeEach: function () {
		mw.config.set( 'wgMetricsPlatformUserExperiments', {
			/* eslint-disable-next-line camelcase */
			active_experiments: [],
			enrolled: [],
			assigned: [],
			/* eslint-disable-next-line camelcase */
			subject_ids: [],
			/* eslint-disable-next-line camelcase */
			sampling_units: []
		} );
	}
} );

QUnit.test( 'getExperiment() - The user is not enrolled in this experiment', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'an_experiment_name' );

	assert.strictEqual( experiment.isEnrolled(), false );
	assert.strictEqual( experiment.getAssignedGroup(), null );
} );

// Test cases for when there are experiments where the user is enrolled
QUnit.module( 'ext.xLab/Experiment - User is logged-in and there are experiments', {
	beforeEach: function () {
		mw.config.set( 'wgMetricsPlatformUserExperiments', {
			enrolled: [
				'fruit',
				'dessert'
			],
			assigned: {
				fruit: 'tropical',
				dessert: 'ice-cream'
			},
			/* eslint-disable-next-line camelcase */
			subject_ids: {
				fruit: '2def9a8f9d8c4f0296268a1c3d2e7fba90298e704070d946536166c832d05652',
				dessert: '788a1970cc9b665222de25cc1a79da7ee1fcaf69b674caba188233ad995ba3d4'
			},
			/* eslint-disable-next-line camelcase */
			sampling_units: {
				fruit: 'mw-user',
				dessert: 'mw-user'
			},
			/* eslint-disable-next-line camelcase */
			active_experiments: [
				'fruit',
				'dessert',
				'lunch'
			]
		} );
	}
} );

QUnit.test( 'getExperiment() - The experiment doesn\'t exist', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'other_experiment' );

	assert.strictEqual( experiment.isEnrolled(), false );
	assert.strictEqual( experiment.getAssignedGroup(), null );
} );

QUnit.test( 'getExperiment() - The user is not enrolled in this experiment', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'lunch' );

	assert.strictEqual( experiment.isEnrolled(), false );
	assert.strictEqual( experiment.getAssignedGroup(), null );
} );

QUnit.test( 'getExperiment() - The user is enrolled in this experiment', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'fruit' );

	assert.strictEqual( experiment.isEnrolled(), true );
	assert.strictEqual( experiment.getAssignedGroup(), 'tropical' );
} );
