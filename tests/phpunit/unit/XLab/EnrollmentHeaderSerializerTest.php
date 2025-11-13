<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Unit\XLab;

use Generator;
use MediaWiki\Extension\MetricsPlatform\XLab\EnrollmentHeaderSerializer;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\EnrollmentHeaderSerializer
 */
class EnrollmentHeaderSerializerTest extends MediaWikiUnitTestCase {

	public function provideSerialize(): Generator {
		yield [ [], '' ];

		yield [
			[
				'assigned' => [],
			],
			'',
		];

		yield [
			[
				'assigned' => [
					'hello' => 'world',
					'foo' => 'bar',
					'baz' => 'qux',
				]
			],
			'X-Experiment-Enrollments: hello=world;foo=bar;baz=qux;',
		];
	}

	/**
	 * @dataProvider provideSerialize
	 */
	public function testSerialize( $enrollments, $expected ): void {
		$this->assertSame( $expected, EnrollmentHeaderSerializer::serialize( $enrollments ) );
	}
}
