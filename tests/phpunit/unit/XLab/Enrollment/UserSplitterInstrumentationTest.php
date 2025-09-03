<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests\Unit\XLab\Enrollment;

use Generator;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\UserSplitterInstrumentation;
use MediaWikiUnitTestCase;
use Wikimedia\Assert\ParameterAssertionException;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\UserSplitterInstrumentation
 */
class UserSplitterInstrumentationTest extends MediaWikiUnitTestCase {
	/**
	 * @dataProvider provideIsSampledInvalidSamplingRatio
	 */
	public function testIsSampledInvalidSamplingRatio( $sampleRatio, array $buckets ) {
		$this->expectException( ParameterAssertionException::class );
		$this->expectExceptionMessage( 'Sample ratio must be in range [0, 1]' );

		$subject = new UserSplitterInstrumentation();
		$userHash = $subject->getUserHash( 3141592654, __METHOD__ );

		$subject->isSampled( $sampleRatio, $buckets, $userHash );
	}

	public static function provideIsSampledInvalidSamplingRatio(): array {
		return [
			'Out of range: negative integer' => [ -1, [] ],
			'Out of range: negative float' => [ -0.1, [] ],
			'Out of range: positive float' => [ 1.1, [] ],
			'Out of range: positive integer' => [ 2, [] ],
		];
	}

	/**
	 * @dataProvider provideSampledAndBucket
	 */
	public function testSampledAndBucket(
		float $ratio, array $buckets, float $userHash, bool $sampled, ?string $bucket
	) {
		$subject = new UserSplitterInstrumentation();
		$this->assertEquals( $sampled, $subject->isSampled( $ratio, $buckets, $userHash ) );
		$this->assertEquals( $bucket, $subject->getBucket( $buckets, $userHash ) );
	}

	public static function provideSampledAndBucket(): Generator {
		// No sample
		yield [
			'ratio' => 0.0, 'buckets' => [],
			'user' => 0.00, 'sampled' => false, 'bucket' => null
		];
		yield [
			'ratio' => 0.0, 'buckets' => [],
			'user' => 0.50, 'sampled' => false, 'bucket' => null
		];
		yield [
			'ratio' => 0.0, 'buckets' => [ 'control', 'treatment' ],
			'user' => 0.00, 'sampled' => false, 'bucket' => 'control'
		];
		yield [
			'ratio' => 0.0, 'buckets' => [ 'control', 'treatment' ],
			'user' => 0.49, 'sampled' => false, 'bucket' => 'control'
		];
		yield [
			'ratio' => 0.0, 'buckets' => [ 'control', 'treatment' ],
			'user' => 0.99, 'sampled' => false, 'bucket' => 'treatment'
		];

		// 50% sample ratio
		yield [
			'ratio' => 0.5, 'buckets' => [],
			'user' => 0.00, 'sampled' => true, 'bucket' => null
		];
		yield [
			'ratio' => 0.5, 'buckets' => [],
			'user' => 0.50, 'sampled' => false, 'bucket' => null
		];
		yield [
			'ratio' => 0.5, 'buckets' => [],
			'user' => 0.99, 'sampled' => false, 'bucket' => null
		];

		// 100% sample ratio
		yield [
			'ratio' => 1.0, 'buckets' => [],
			'user' => 0.00, 'sampled' => true, 'bucket' => null
		];
		yield [ 'ratio' => 1.0, 'buckets' => [],
			'user' => 0.50, 'sampled' => true, 'bucket' => null
		];
		yield [
			'ratio' => 1.0, 'buckets' => [],
			'user' => 0.99, 'sampled' => true, 'bucket' => null
		];
		yield [
			'ratio' => 1.0, 'buckets' => [ 'a', 'b', 'c' ],
			'user' => 0.00, 'sampled' => true, 'bucket' => 'a'
		];
		yield [
			'ratio' => 1.0, 'buckets' => [ 'a', 'b', 'c' ],
			'user' => 0.33, 'sampled' => true, 'bucket' => 'a'
		];
		yield [
			'ratio' => 1.0, 'buckets' => [ 'a', 'b', 'c' ],
			'user' => 0.99, 'sampled' => true, 'bucket' => 'c'
		];

		// 10% sample
		yield [
			'ratio' => 0.1, 'buckets' => [ 'control', 'a', 'b', 'c' ],
			'user' => 0.00, 'sampled' => true, 'bucket' => 'control'
		];
		yield [
			'ratio' => 0.1, 'buckets' => [ 'control', 'a', 'b', 'c' ],
			'user' => 0.024, 'sampled' => true, 'bucket' => 'control'
		];
		yield [
			'ratio' => 0.1, 'buckets' => [ 'control', 'a', 'b', 'c' ],
			'user' => 0.025, 'sampled' => false, 'bucket' => 'control'
		];
		yield [
			'ratio' => 0.1, 'buckets' => [ 'control', 'a', 'b', 'c' ],
			'user' => 0.99, 'sampled' => false, 'bucket' => 'c'
		];
	}

	public function testScenarioRollout100() {
		// Drop buckets from [ 'control', 'treatment' ], a 50 / 50 split, to [ 'treatment' ] and
		// increase sample to 100%.
		$userHash = 0.813;
		$buckets = [ 'treatment' ];
		$subject = new UserSplitterInstrumentation();
		$this->assertFalse( $subject->isSampled( 0.0, $buckets, $userHash ) );
		$this->assertEquals( 'treatment', $subject->getBucket( $buckets, 0.0 ) );
		$this->assertTrue( $subject->isSampled( 0.9, $buckets, $userHash ) );
		$this->assertEquals( 'treatment', $subject->getBucket( $buckets, 0.9 ) );
	}

	/**
	 * @dataProvider provideGetUserHash
	 */
	public function testGetUserHash( $expected, $userId, $experimentName ) {
		$subject = new UserSplitterInstrumentation();
		$actual = $subject->getUserHash( $userId, $experimentName );
		// Truncate for easier testing
		$actualTrunc = (float)sprintf( '%.3f', $actual );
		$this->assertSame( $expected, $actualTrunc );
	}

	public static function provideGetUserHash(): array {
		return [
			'Valid: 1' => [ 0.051, 3, 'dog' ],
			'Valid: 10' => [ 0.408, 30, 'cat' ],
			'Valid: 100' => [ 0.358, 108, 'tiger' ],
			'Valid: 1000' => [ 0.015, 3803, 'elephant' ],
			'Valid: 10000' => [ 0.035, 88088, 'giraffe' ],
			'Valid: 100000' => [ 0.447, 418975, 'penguin' ],
			'Valid: 1000000' => [ 0.501, 5374208, 'bear' ],
			'Valid: 10000000' => [ 0.988, 67123159, 'horse' ],
		];
	}

}
