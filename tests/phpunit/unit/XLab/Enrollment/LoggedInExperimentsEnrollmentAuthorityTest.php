<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests;

use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentRequest;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\LoggedInExperimentsEnrollmentAuthority;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\LoggedInExperimentsEnrollmentAuthority
 */
class LoggedInExperimentsEnrollmentAuthorityTest extends MediaWikiUnitTestCase {
	private UserIdentity $user;
	private EnrollmentRequest $request;
	private EnrollmentResultBuilder $result;
	private CentralIdLookup $centralIdLookup;
	private LoggedInExperimentsEnrollmentAuthority $authority;

	public function setUp(): void {
		parent::setUp();

		$this->user = new UserIdentityValue( 1, self::class );

		$this->request = $this->createMock( EnrollmentRequest::class );
		$this->request->expects( $this->any() )
			->method( 'getGlobalUser' )
			->willReturn( $this->user );

		$this->centralIdLookup = $this->createMock( CentralIdLookup::class );
		$this->centralIdLookup->expects( $this->any() )
			->method( 'centralIdFromLocalUser' )
			->with( $this->user )
			->willReturn( 2 );

		$this->result = $this->createMock( EnrollmentResultBuilder::class );

		$this->authority = new LoggedInExperimentsEnrollmentAuthority( $this->centralIdLookup );
	}

	public function testNoActiveExperiments(): void {
		$this->request->expects( $this->once() )
			->method( 'getActiveLoggedInExperiments' )
			->willReturn( [] );

		$this->result->expects( $this->never() )
			->method( 'addExperiment' );

		$this->result->expects( $this->never() )
			->method( 'addEnrollment' );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testOneActiveExperiment(): void {
		$this->request->expects( $this->once() )
			->method( 'getActiveLoggedInExperiments' )
			->willReturn( [
				[
					'name' => 'foo',
					'sample' => [
						'rate' => 1,
					],
					'groups' => [
						'control',
						'treatment',
					],
				],
			] );

		$this->result->expects( $this->once() )
			->method( 'addExperiment' )
			->with( 'foo' );

		$this->result->expects( $this->once() )
			->method( 'addEnrollment' )
			->with(
				'foo',
				'control',
				'377195904c99497c2cdb7aaecaf541ca717f34e5357dace55ebb1711d54190c2',
				'mw-user'
			);

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testMultipleActiveExperiments(): void {
		$this->request->expects( $this->once() )
			->method( 'getActiveLoggedInExperiments' )
			->willReturn( [
				[
					'name' => 'foo',
					'sample' => [
						'rate' => 1,
					],
					'groups' => [
						'control',
						'treatment',
					],
				],
				[
					'name' => 'bar',
					'sample' => [
						'rate' => 0.5,
					],
					'groups' => [
						'control',
						'treatment',
					]
				]
			] );

		// withConsecutive was deprecated in PHPUnit 9 and removed in 10. The following is based on the replacement
		// suggested by Tomas Votruba in
		// https://tomasvotruba.com/blog/how-to-upgrade-deprecated-phpunit-with-consecutive.

		$addExperimentInvokedCount = $this->exactly( 2 );

		$this->result->expects( $addExperimentInvokedCount )
			->method( 'addExperiment' )
			->willReturnCallback( function ( ...$parameters ) use ( $addExperimentInvokedCount ) {
				if ( $addExperimentInvokedCount->getInvocationCount() === 1 ) {
					$this->assertSame(
						[ 'foo' ],
						$parameters
					);
				}

				if ( $addExperimentInvokedCount->getInvocationCount() === 2 ) {
					$this->assertSame(
						[ 'bar' ],
						$parameters
					);
				}
			} );

		$addEnrollmentInvokedCount = $this->exactly( 2 );

		$this->result->expects( $addEnrollmentInvokedCount )
			->method( 'addEnrollment' )
			->willReturnCallback( function ( ...$parameters ) use ( $addEnrollmentInvokedCount ) {
				if ( $addEnrollmentInvokedCount->getInvocationCount() === 1 ) {
					$this->assertSame(
						[
							'foo',
							'control',
							'377195904c99497c2cdb7aaecaf541ca717f34e5357dace55ebb1711d54190c2',
							'mw-user'
						],
						$parameters
					);
				}

				if ( $addEnrollmentInvokedCount->getInvocationCount() === 2 ) {
					$this->assertSame(
						[
							'bar',
							'treatment',
							'92bd577d056dc2d6fe69083f638d4ce8bf4e8e4b88b351bcb8bbdf2dcef6a437',
							'mw-user'
						],
						$parameters
					);
				}
			} );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public function testNoCentralID(): void {
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->expects( $this->once() )
			->method( 'centralIdFromLocalUser' )
			->with( $this->user )
			->willReturn( 0 );

		$this->request->expects( $this->never() )
			->method( 'getActiveLoggedInExperiments' );

		$authority = new LoggedInExperimentsEnrollmentAuthority( $centralIdLookup );
		$authority->enrollUser( $this->request, $this->result );
	}
}
