<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentAuthority;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EveryoneExperimentsEnrollmentAuthority;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\LoggedInExperimentsEnrollmentAuthority;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\OverridesEnrollmentAuthority;
use MediaWiki\Extension\MetricsPlatform\XLab\ExperimentManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

return [
	'MetricsPlatform.ConfigsFetcher' => static function ( MediaWikiServices $services )  {
		$options = new ServiceOptions(
			InstrumentConfigsFetcher::CONSTRUCTOR_OPTIONS,
			$services->getMainConfig()
		);
		return new InstrumentConfigsFetcher(
			$options,
			$services->getMainObjectStash(),
			$services->getHttpRequestFactory(),
			$services->getService( 'MetricsPlatform.Logger' ),
			$services->getStatsFactory()->withComponent( 'MetricsPlatform' ),
			$services->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() )
		);
	},
	'MetricsPlatform.Logger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'MetricsPlatform' );
	},
	'MetricsPlatform.XLab.EveryoneExperimentsEnrollmentAuthority' =>
		static function ( MediaWikiServices $services ): EveryoneExperimentsEnrollmentAuthority {
			return new EveryoneExperimentsEnrollmentAuthority(
				$services->getService( 'MetricsPlatform.Logger' )
			);
		},
	'MetricsPlatform.XLab.LoggedInExperimentsEnrollmentAuthority' =>
		static function ( MediaWikiServices $services ): LoggedInExperimentsEnrollmentAuthority {
			return new LoggedInExperimentsEnrollmentAuthority( $services->getCentralIdLookup() );
		},
	'MetricsPlatform.XLab.OverridesEnrollmentAuthority' =>
		static function ( MediaWikiServices $services ): OverridesEnrollmentAuthority {
			return new OverridesEnrollmentAuthority( new ServiceOptions(
				OverridesEnrollmentAuthority::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			) );
		},
	'MetricsPlatform.XLab.EnrollmentAuthority' => static function ( MediaWikiServices $services ): EnrollmentAuthority {
		return new EnrollmentAuthority(
			$services->getService( 'MetricsPlatform.XLab.EveryoneExperimentsEnrollmentAuthority' ),
			$services->getService( 'MetricsPlatform.XLab.LoggedInExperimentsEnrollmentAuthority' ),
			$services->getService( 'MetricsPlatform.XLab.OverridesEnrollmentAuthority' )
		);
	},
	'MetricsPlatform.XLab.ExperimentManager' => static function ( MediaWikiServices $services ): ExperimentManager {
		return new ExperimentManager(
			$services->getService( 'MetricsPlatform.Logger' ),
			EventLogging::getMetricsPlatformClient()
		);
	}
];
