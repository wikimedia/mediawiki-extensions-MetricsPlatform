<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

return [
	'MetricsPlatform.InstrumentConfigsFetcher' => static function ( MediaWikiServices $services )  {
		$options = new ServiceOptions(
			InstrumentConfigsFetcher::CONSTRUCTOR_OPTIONS,
			$services->getMainConfig()
		);
		return new InstrumentConfigsFetcher(
			$options,
			$services->getMainWANObjectCache(),
			$services->getHttpRequestFactory(),
			$services->getService( 'MetricsPlatform.Logger' ),
			$services->getStatsFactory()->withComponent( 'MetricsPlatform' ),
			$services->getFormatterFactory()->getStatusFormatter( RequestContext::getMain() )
		);
	},
	'MetricsPlatform.Logger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'MetricsPlatform' );
	},
];
