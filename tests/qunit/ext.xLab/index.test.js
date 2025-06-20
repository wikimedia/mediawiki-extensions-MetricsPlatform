// Test cases when the user is not logged-in or
// `MetricsPlatformEnableExperiments` is falsy
// (wgMetricsPlatformUserExperiments will be 'undefined')
QUnit.module( 'ext.xLab', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.originalMPOCookie = mw.cookie.get( 'mpo' );
		this.originalMPUserExperiments = mw.config.get( 'wgMetricsPlatformUserExperiments' );

		mw.cookie.set( 'mpo', null );
	},
	afterEach: function () {
		mw.config.set( 'wgMetricsPlatformUserExperiments', this.originalMPUserExperiments );
		mw.cookie.set( 'mpo', this.originalMPOCookie );
	}
} ) );

QUnit.test( 'getExperiment() - handles invalid config', ( assert ) => {
	const e = mw.xLab.getExperiment( 'an_experiment_name' );

	assert.strictEqual( typeof e, 'object' );
	assert.strictEqual( e.getAssignedGroup(), null );
} );

QUnit.test.each(
	'getExperiment()',
	{
		'handles unknown experiment': [ 'elevenses', null ],
		'handles active experiment with no enrollment': [ 'lunch', null ],
		'handles active experiment with enrollment': [ 'fruit', 'tropical' ]
	},
	( assert, [ experimentName, expectedAssignedGroup ] ) => {
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

		assert.strictEqual(
			mw.xLab.getExperiment( experimentName ).getAssignedGroup(),
			expectedAssignedGroup
		);
	}
);

// Test cases for the overriding feature
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
