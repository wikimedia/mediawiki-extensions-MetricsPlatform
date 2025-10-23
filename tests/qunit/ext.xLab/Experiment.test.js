const { Experiment, UnenrolledExperiment, OverriddenExperiment } = mw.xLab;

QUnit.module( 'ext.xLab/Experiment', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.metricsClient = {
			submitInteraction: this.sandbox.spy(),
			getStreamConfig: this.sandbox.stub()
		};

		// Note well that Experiment#constructor() is package-private. Calling it outside xLab is
		// not supported.

		this.everyoneExperiment = new Experiment(
			this.metricsClient,
			'hello_world',
			'A',
			'awaiting',
			'edge-unique',
			'xLab'
		);

		this.loggedInExperiment = new Experiment(
			this.metricsClient,
			'my-awesome-experiment',
			'B',
			'0x0ff1ce',
			'mw-user',
			'xLab'
		);
	}
} ) );

QUnit.test.each(
	'isAssignedGroup()',
	{
		A: [ 'A', true ],
		B: [ 'B', false ],
		'Multiple, including A': [ [ 'B', 'A' ], true ],
		'Multiple, excluding A': [ [ 'B', 'C' ], false ]
	},
	function ( assert, [ groups, expected ] ) {
		assert.strictEqual( this.everyoneExperiment.isAssignedGroup( ...groups ), expected );
	}
);

QUnit.test.each(
	'send() - sends events via metricsClient',
	[
		[
			'everyoneExperiment',
			{
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				coordinator: 'xLab'
			}
		],
		[
			'loggedInExperiment',
			{
				enrolled: 'my-awesome-experiment',
				assigned: 'B',
				subject_id: '0x0ff1ce',
				sampling_unit: 'mw-user',
				coordinator: 'xLab'
			}
		]
	],
	function ( assert, [ propertyName, expectedExperiment ] ) {
		this[ propertyName ].send( 'Hello, World!' );

		assert.strictEqual( this.metricsClient.submitInteraction.called, true );
		assert.deepEqual( this.metricsClient.submitInteraction.firstCall.args, [
			'product_metrics.web_base',
			'/analytics/product_metrics/web/base/1.4.2',
			'Hello, World!',
			{
				experiment: expectedExperiment
			}
		] );
	}
);

QUnit.test( 'send() - overrides experiment field', function ( assert ) {
	this.everyoneExperiment.send( 'Hello, World!', {
		experiment: {
			foo: 'bar',
			baz: 'qux'
		}
	} );

	assert.strictEqual( this.metricsClient.submitInteraction.called, true );
	assert.deepEqual( this.metricsClient.submitInteraction.firstCall.args, [
		'product_metrics.web_base',
		'/analytics/product_metrics/web/base/1.4.2',
		'Hello, World!',
		{
			experiment: {
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				coordinator: 'xLab'
			}
		}
	] );
} );

QUnit.test( 'send() - overriding stream and schema', function ( assert ) {
	this.metricsClient.getStreamConfig.returns( {} );

	this.everyoneExperiment.setStream( 'my_awesome_stream' )
		.setSchema( '/my/awesome/schema/0.0.1' )
		.send( 'Hello, World!' );

	assert.strictEqual( this.metricsClient.submitInteraction.called, true );
	assert.deepEqual( this.metricsClient.submitInteraction.firstCall.args, [
		'my_awesome_stream',
		'/my/awesome/schema/0.0.1',
		'Hello, World!',
		{
			experiment: {
				enrolled: 'hello_world',
				assigned: 'A',
				subject_id: 'awaiting',
				sampling_unit: 'edge-unique',
				coordinator: 'xLab'
			}
		}
	] );
} );

QUnit.test( 'setStream() - warns when stream isn\'t registered', function ( assert ) {
	this.sandbox.stub( console, 'warn' );

	this.everyoneExperiment.setStream( 'my_awesome_stream' );

	assert.strictEqual( this.metricsClient.getStreamConfig.called, true );

	// eslint-disable-next-line no-console
	assert.strictEqual( console.warn.called, true );
} );

// ---

QUnit.module( 'ext.xLab/UnenrolledExperiment' );

QUnit.test( 'constructor()', ( assert ) => {
	// Note well that UnenrolledExperiment#constructor() is package-private. Calling it outside xLab
	// is not supported.

	const e = new UnenrolledExperiment( 'hello_world' );

	assert.propContains(
		e,
		{
			metricsClient: null,
			name: 'hello_world',
			assignedGroup: null,
			subjectId: null,
			samplingUnit: null,
			coordinator: 'xLab'
		}
	);
} );

QUnit.test( 'send()', ( assert ) => {
	const e = new UnenrolledExperiment( 'hello_world' );

	e.send( 'Hello, World!', {
		experiment: {
			foo: 'bar',
			baz: 'qux'
		}
	} );

	assert.strictEqual(
		true, true,
		'send() shouldn\'t throw an error'
	);
} );

QUnit.test( 'setStream() - doesn\'t trigger an error', ( assert ) => {
	assert.expect( 0 );

	const e = new UnenrolledExperiment( 'hello_world' );

	e.setStream( 'my_awesome_stream' );
} );

// ---

QUnit.module( 'ext.xLab/OverriddenExperiment' );

QUnit.test( 'constructor()', ( assert ) => {
	// Note well that OverriddenExperiment#constructor() is package-private. Calling it outside xLab
	// is not supported.

	const e = new OverriddenExperiment(
		'hello_world',
		'foo',
		'overridden',
		'mw-user'
	);

	assert.propContains(
		e,
		{
			metricsClient: null,
			name: 'hello_world',
			assignedGroup: 'foo',
			subjectId: 'overridden',
			samplingUnit: 'mw-user',
			coordinator: 'forced'
		}
	);
} );

QUnit.test( 'send()', function ( assert ) {
	const action = 'Hello, World!';
	const interactionData = {
		experiment: {
			foo: 'bar',
			baz: 'qux'
		}
	};

	this.sandbox.mock( console )
		.expects( 'log' )
		.once()
		.withExactArgs(
			action,
			JSON.stringify( interactionData, null, 2 )
		);

	const e = new OverriddenExperiment(
		'hello_world',
		'foo',
		'overridden',
		'mw-user'
	);

	e.send( action, interactionData );

	assert.strictEqual(
		true, true,
		'send() shouldn\'t throw an error'
	);
} );

QUnit.test( 'setStream() - doesn\'t trigger an error', ( assert ) => {
	assert.expect( 0 );

	const e = new OverriddenExperiment( 'foo_bar' );

	e.setStream( 'my_awesome_stream' );
} );
