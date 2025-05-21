<?php

namespace MediaWiki\Extension\MetricsPlatform\Tests;

use Generator;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentRequest;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\OverridesEnrollmentAuthority;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\OverridesEnrollmentAuthority
 */
class OverridesEnrollmentAuthorityTest extends MediaWikiUnitTestCase {
	private EnrollmentRequest $request;
	private EnrollmentResultBuilder $result;
	private OverridesEnrollmentAuthority $authority;

	public function setUp(): void {
		parent::setUp();

		$this->request = $this->createMock( EnrollmentRequest::class );
		$this->result = $this->createMock( EnrollmentResultBuilder::class );

		$options = new ServiceOptions(
			OverridesEnrollmentAuthority::CONSTRUCTOR_OPTIONS,
			[
				'MetricsPlatformEnableExperimentOverrides' => true,
			]
		);
		$this->authority = new OverridesEnrollmentAuthority( $options );
	}

	public function testCookieAndQueryAreEmpty(): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEnrollmentOverridesFromCookie' )
			->willReturn( '' );

		$this->request->expects( $this->once() )
			->method( 'getRawEnrollmentOverridesFromQuery' )
			->willReturn( '' );

		$this->result->expects( $this->never() )
			->method( 'addOverride' );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	/**
	 * @dataProvider provideCookieAndQuery
	 */
	public function testCookieAndQuery(
		string $rawCookie,
		string $rawQuery,
		array $expectedOverrides
	): void {
		$this->request->expects( $this->once() )
			->method( 'getRawEnrollmentOverridesFromCookie' )
			->willReturn( $rawCookie );

		$this->request->expects( $this->once() )
			->method( 'getRawEnrollmentOverridesFromQuery' )
			->willReturn( $rawQuery );

		$addOverrideInvokedCount = $this->exactly( count( $expectedOverrides ) );

		$this->result->expects( $addOverrideInvokedCount )
			->method( 'addOverride' )
			->willReturnCallback( function ( ...$parameters ) use ( $addOverrideInvokedCount, $expectedOverrides ) {
				$index = $addOverrideInvokedCount->getInvocationCount() - 1;

				$this->assertSame( $expectedOverrides[$index], $parameters );
			} );

		$this->authority->enrollUser( $this->request, $this->result );
	}

	public static function provideCookieAndQuery(): Generator {
		yield [
			'foo:bar',
			'',
			[
				[ 'foo', 'bar' ],
			]
		];
		yield [
			'',
			'qux:quux',
			[
				[ 'qux', 'quux' ],
			]
		];
		yield [
			'foo:bar',
			'qux:quux',
			[
				[ 'foo', 'bar' ],
				[ 'qux', 'quux' ],
			],
		];
		yield [
			'foo:bar;qux:quux',
			'',
			[
				[ 'foo', 'bar' ],
				[ 'qux', 'quux' ],
			]
		];
	}

	public function testDisabled(): void {
		$this->request->expects( $this->never() )
			->method( 'getRawEnrollmentOverridesFromCookie' );

		$this->request->expects( $this->never() )
			->method( 'getRawEnrollmentOverridesFromQuery' );

		$this->result->expects( $this->never() )
			->method( 'addOverride' );

		$options = new ServiceOptions(
			OverridesEnrollmentAuthority::CONSTRUCTOR_OPTIONS,
			[
				'MetricsPlatformEnableExperimentOverrides' => false,
			]
		);
		$authority = new OverridesEnrollmentAuthority( $options );
		$authority->enrollUser( $this->request, $this->result );
	}
}
