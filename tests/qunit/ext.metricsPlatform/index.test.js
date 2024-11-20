QUnit.module( 'ext.metricsPlatform', {
	beforeEach() {
		this.originalMPOCookie = mw.cookie.get( 'mpo' );

		mw.cookie.set( 'mpo', null );
	},

	afterEach() {
		mw.cookie.set( 'mpo', this.originalMPOCookie );
	}
} );

QUnit.test( 'overrideExperimentEnrollment() - single call', ( assert ) => {
	mw.metricsPlatform.overrideExperimentEnrollment( 'foo', 'bar', 'baz' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar:baz' );
} );

QUnit.test( 'overrideExperimentEnrollment() - multiple calls', ( assert ) => {
	mw.metricsPlatform.overrideExperimentEnrollment( 'foo', 'bar', 'baz' );
	mw.metricsPlatform.overrideExperimentEnrollment( 'qux', 'quux', 'corge' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar:baz;qux:quux:corge' );
} );

QUnit.test( 'overrideExperimentEnrollment() - multiple identical calls', ( assert ) => {
	mw.metricsPlatform.overrideExperimentEnrollment( 'foo', 'bar', 'baz' );
	mw.metricsPlatform.overrideExperimentEnrollment( 'qux', 'quux', 'corge' );
	mw.metricsPlatform.overrideExperimentEnrollment( 'foo', 'bar', 'baz' );
	mw.metricsPlatform.overrideExperimentEnrollment( 'qux', 'quux', 'corge' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar:baz;qux:quux:corge' );
} );

QUnit.test( 'overrideExperimentEnrollment() - multiple calls with different $featureVariantValue', ( assert ) => {
	mw.metricsPlatform.overrideExperimentEnrollment( 'foo', 'bar', 'baz' );
	mw.metricsPlatform.overrideExperimentEnrollment( 'foo', 'bar', 'qux' );

	assert.strictEqual( mw.cookie.get( 'mpo' ), 'foo:bar:qux' );
} );
