<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Psr\Log\LoggerInterface;

return [
	'MetricsPlatform.InstrumentConfigs' => static function ( MediaWikiServices $services )  {
		$options = new ServiceOptions(
			InstrumentConfigsFetcher::CONSTRUCTOR_OPTIONS,
			$services->getMainConfig()
		);
		$fetcher = new InstrumentConfigsFetcher(
			$options,
			$services->getMainWANObjectCache(),
			$services->getHttpRequestFactory(),
			$services->getService( 'MetricsPlatform.Logger' )
		);
		return $fetcher->getInstrumentConfigs();
	},
	'MetricsPlatform.Logger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'MetricsPlatform' );
	},
];
