<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab\Enrollment;

use Wikimedia\Assert\Assert;

/**
 * Deterministic sample and bucketing based on user IDs.
 *
 * The caller takes care of turning a user ID into a deterministic hash with
 * uniform probability distribution (see UserHashGenerate).
 *
 * Given an example user that is assigned 0.421 and 3 buckets (A, B, C), it works as follows:
 *
 * - The assigned float is scaled to cover the three buckets, in #scaledHash().
 *   0.421 * 3 = 1.263
 *
 * - Each whole number represents a bucket. This case we're in bucket B.
 *   A = 0.x, B = 1.x, C = 2.x
 *
 * - The fraction within each number represents the sample, so if our sample ratio
 *   is 0.5, than x.00 to x.50 would be sampled, and x.50 to x.99 would be unsampled.
 *   In this case we're 1.263 which is sampled, and in bucket B.
 *
 * @license GPL-2.0-or-later
 * @internal
 */
class UserSplitterInstrumentation {

	/**
	 * Get hash of a user ID as a float between 0.0 (inclusive) and 1.0 (non-inclusive)
	 * concatenated with an experiment name.
	 *
	 * @param int $userId
	 * @param string $experimentName
	 * @return float
	 */
	public function getUserHash( int $userId, string $experimentName ): float {
		$subjectId = $this->getSubjectId( $userId, $experimentName );
		return intval( substr( $subjectId, 0, 6 ), 16 ) / ( 0xffffff + 1 );
	}

	/**
	 * Get hash of a user ID as a 'sha256' hash concatenated with an experiment name.
	 *
	 * @param int $userId
	 * @param string $experimentName
	 * @return string
	 */
	public function getSubjectId( int $userId, string $experimentName ): string {
		return hash( 'sha256', $userId . $experimentName );
	}

	/**
	 * Whether given user is in the sample.
	 *
	 * Should be called before getBucket().
	 *
	 * @param float $sampleRatio
	 * @param array $buckets
	 * @param float $userHash
	 * @return bool True if sampled, false if unsampled.
	 */
	public function isSampled( float $sampleRatio, array $buckets, float $userHash ): bool {
		Assert::parameter(
			$sampleRatio >= 0 && $sampleRatio <= 1,
			'sampleRatio',
			'Sample ratio must be in range [0, 1]'
		);

		// Take the right of the decimal.
		$sample = fmod( $this->scaledHash( $userHash, $buckets ), 1 );
		return $sample < $sampleRatio;
	}

	/**
	 * Which bucket a given user is in.
	 *
	 * This does NOT imply sample and should usually be called after isSampled().
	 *
	 * @param array $buckets
	 * @param float $userHash
	 * @return mixed|null Bucket name or null if buckets are unused.
	 */
	public function getBucket( array $buckets, float $userHash ) {
		if ( $buckets === [] ) {
			return null;
		}

		// Get the bucket index (int is akin to floor/truncate, but as int instead of float)
		$index = (int)$this->scaledHash( $userHash, $buckets );

		return $buckets[ $index ];
	}

	/**
	 * @param float $userHash
	 * @param array $buckets
	 * @return float Integer component is the bucket index (from 0 to count-1), fractional component is the sample rate.
	 */
	private function scaledHash( float $userHash, array $buckets ): float {
		return $userHash * max( 1, count( $buckets ) );
	}

}
