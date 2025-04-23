// Test cases when the user is not logged-in or
// `MetricsPlatformEnableExperiments` is falsy
// (wgMetricsPlatformUserExperiments will be 'undefined')
QUnit.module( 'ext.xLab/Experiment - User is not logged-in or MetricsPlatformEnableExperiments is falsy', {
	beforeEach: function () {
		mw.config.set( 'wgMetricsPlatformUserExperiments', undefined );
	}
} );

QUnit.test( 'getExperiment() - The user is not enrolled in this experiment', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'an_experiment_name' );

	assert.strictEqual( experiment.getAssignedGroup(), null );
} );

// Test cases when the user is logged-in and there are no experiments
// (wgMetricsPlatformUserExperiments will contain only empty arrays')
QUnit.module( 'ext.xLab/Experiment - User is logged-in and there are no experiments', {
	beforeEach: function () {
		mw.config.set( 'wgMetricsPlatformUserExperiments', {

			active_experiments: [],
			enrolled: [],
			assigned: [],

			subject_ids: [],

			sampling_units: [],
			overrides: []
		} );
	}
} );

QUnit.test( 'getExperiment() - The user is not enrolled in this experiment', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'an_experiment_name' );

	assert.strictEqual( experiment.getAssignedGroup(), null );
} );

// Test cases when the user is logged-in, there are experiments but the
// user is not enrolled for any of them
QUnit.module( 'ext.xLab/Experiment - User is logged-in, there are experiments but not enrollments', {
	beforeEach: function () {
		mw.config.set( 'wgMetricsPlatformUserExperiments', {

			active_experiments: [
				'one_experiment',
				'other_experiment'
			],
			enrolled: [],
			assigned: [],

			subject_ids: [],

			sampling_units: [],
			overrides: []
		} );
	}
} );

QUnit.test( 'getExperiment() - The experiment doesn\'t exist', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'an_experiment_that_doesnt_exist' );

	assert.strictEqual( experiment.getAssignedGroup(), null );
} );

QUnit.test( 'getExperiment() - The user is not enrolled in this experiment', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'one_experiment' );

	assert.strictEqual( experiment.getAssignedGroup(), null );
} );

// Test cases when there are experiments where the user is enrolled
QUnit.module( 'ext.xLab/Experiment - User is logged-in and enrolled in some experiments', {
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

			subject_ids: {
				fruit: '2def9a8f9d8c4f0296268a1c3d2e7fba90298e704070d946536166c832d05652',
				dessert: '788a1970cc9b665222de25cc1a79da7ee1fcaf69b674caba188233ad995ba3d4'
			},

			sampling_units: {
				fruit: 'mw-user',
				dessert: 'mw-user'
			},

			active_experiments: [
				'fruit',
				'dessert',
				'lunch'
			],
			overrides: []
		} );
	}
} );

QUnit.test( 'getExperiment() - The experiment doesn\'t exist', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'other_experiment' );

	assert.strictEqual( experiment.getAssignedGroup(), null );
} );

QUnit.test( 'getExperiment() - The user is not enrolled in this experiment', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'lunch' );

	assert.strictEqual( experiment.getAssignedGroup(), null );
} );

QUnit.test( 'getExperiment() - The user is enrolled in this experiment', ( assert ) => {
	const experiment = mw.xLab.getExperiment( 'fruit' );

	assert.strictEqual( experiment.getAssignedGroup(), 'tropical' );
} );

// Test cases for the overriding feature
QUnit.module( 'ext.xLab', {
	beforeEach() {
		this.originalMPOCookie = mw.cookie.get( 'mpo' );

		mw.cookie.set( 'mpo', null );
	},

	afterEach() {
		mw.cookie.set( 'mpo', this.originalMPOCookie );
	}
} );

QUnit.test( 'overrideExperimentGroup() - single call', ( assert ) => {
	mw.xLab.overrideExperimentGroup( 'foo', 'bar' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar' );
} );

QUnit.test( 'overrideExperimentGroup() - multiple calls', ( assert ) => {
	mw.xLab.overrideExperimentGroup( 'foo', 'bar' );
	mw.xLab.overrideExperimentGroup( 'qux', 'quux' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar;qux:quux' );
} );

QUnit.test( 'overrideExperimentGroup() - multiple identical calls', ( assert ) => {
	mw.xLab.overrideExperimentGroup( 'foo', 'bar' );
	mw.xLab.overrideExperimentGroup( 'qux', 'quux' );
	mw.xLab.overrideExperimentGroup( 'foo', 'bar' );
	mw.xLab.overrideExperimentGroup( 'qux', 'quux' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar;qux:quux' );
} );

QUnit.test( 'overrideExperimentGroup() - multiple calls with different $groupName', ( assert ) => {
	mw.xLab.overrideExperimentGroup( 'foo', 'bar' );
	mw.xLab.overrideExperimentGroup( 'foo', 'baz' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:baz' );
} );
