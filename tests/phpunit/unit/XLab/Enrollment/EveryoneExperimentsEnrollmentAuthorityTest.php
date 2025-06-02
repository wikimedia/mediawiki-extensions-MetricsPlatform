<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests;

use Generator;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentRequest;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EveryoneExperimentsEnrollmentAuthority;
use MediaWikiUnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EveryoneExperimentsEnrollmentAuthority
 */
class EveryoneExperimentsEnrollmentAuthorityTest extends MediaWikiUnitTestCase {
	private EnrollmentRequest $request;
	private EnrollmentResultBuilder $result;
	private LoggerInterface $logger;
	private EveryoneExperimentsEnrollmentAuthority $authority;

	public function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock( EnrollmentRequest::class );
		$this->result = $this->createMock( EnrollmentResultBuilder::class );

		$this->logger = $this->createMock( LoggerInterface::class );
		$this->authority = new EveryoneExperimentsEnrollmentAuthority( $this->logger );
	}

	public function testHeaderIsEmpty(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEveryoneExperimentsEnrollments' )
			->willReturn( '' );

		$this->result->expects( $this->never() )
			->method( 'addExperiment' );

		$this->result->expects( $this->never() )
			->method( 'addAssignment' );

		$this->logger->expects( $this->never() )
			->method( 'error' );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testHeaderHasOneAssignment(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEveryoneExperimentsEnrollments' )
			->willReturn( 'foo=bar;' );

		$this->result->expects( $this->once() )
			->method( 'addExperiment' )
			->with( 'foo', 'awaiting', 'edge-unique' );

		$this->result->expects( $this->once() )
			->method( 'addAssignment' )
			->with( 'foo', 'bar' );

		$this->logger->expects( $this->never() )
			->method( 'error' );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testHeaderHasMultipleAssignments(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEveryoneExperimentsEnrollments' )
			->willReturn( 'foo=bar;qux=quux;' );

		// withConsecutive was deprecated in PHPUnit 9 and removed in 10. The following is based on the replacement
		// suggested by Tomas Votruba in
		// https://tomasvotruba.com/blog/how-to-upgrade-deprecated-phpunit-with-consecutive.

		$addExperimentInvokedCount = $this->exactly( 2 );

		$this->result->expects( $addExperimentInvokedCount )
			->method( 'addExperiment' )
			->willReturnCallback( function ( ...$parameters ) use ( $addExperimentInvokedCount ) {
				if ( $addExperimentInvokedCount->getInvocationCount() === 1 ) {
					$this->assertSame(
						[ 'foo', 'awaiting', 'edge-unique' ],
						$parameters
					);
				}

				if ( $addExperimentInvokedCount->getInvocationCount() === 2 ) {
					$this->assertSame(
						[ 'qux', 'awaiting', 'edge-unique' ],
						$parameters
					);
				}
			} );

		$addEnrollmentInvokedCount = $this->exactly( 2 );

		$this->result->expects( $addEnrollmentInvokedCount )
			->method( 'addAssignment' )
			->willReturnCallback( function ( ...$parameters ) use ( $addEnrollmentInvokedCount ) {
				if ( $addEnrollmentInvokedCount->getInvocationCount() === 1 ) {
					$this->assertSame(
						[ 'foo', 'bar', false ],
						$parameters
					);
				}

				if ( $addEnrollmentInvokedCount->getInvocationCount() === 2 ) {
					$this->assertSame(
						[ 'qux', 'quux', false ],
						$parameters
					);
				}
			} );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	/**
	 * @dataProvider provideMalformedHeader
	 */
	public function testHeaderIsMalformed(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEveryoneExperimentsEnrollments' )
			->willReturn( 'foo=bar;qux;' );

		$this->result->expects( $this->never() )
			->method( 'addExperiment' );

		$this->result->expects( $this->never() )
			->method( 'addAssignment' );

		$this->logger->expects( $this->once() )
			->method( 'error' )
			->with(
				'The X-Experiment-Enrollments header could not be parsed properly. The header is malformed.'
			);

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public static function provideMalformedHeader(): Generator {
		yield [ 'foo=' ];

		// Assert that the result is only updated _after_ the header is parsed.
		yield [ 'foo=bar;qux=' ];
	}
}
