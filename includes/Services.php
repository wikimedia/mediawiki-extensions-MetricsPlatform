<?php

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\MediaWikiServices;

class Services {
	public static function getConfigsFetcher(): InstrumentConfigsFetcher {
		return MediaWikiServices::getInstance()->getService( 'MetricsPlatform.ConfigsFetcher' );
	}
}
