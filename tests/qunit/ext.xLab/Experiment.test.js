const { Experiment } = mw.xLab;

QUnit.module( 'ext.xLab/Experiment', () => {
	const e = new Experiment( 'hello_world', 'A' );

	QUnit.test.each(
		'isAssignedGroup()',
		{
			A: [ 'A', true ],
			B: [ 'B', false ],
			'Multiple, including A': [ [ 'B', 'A' ], true ],
			'Multiple, excluding A': [ [ 'B', 'C' ], false ]
		},
		( assert, [ groups, expected ] ) => {
			assert.strictEqual( e.isAssignedGroup( ...groups ), expected );
		}
	);
} );
