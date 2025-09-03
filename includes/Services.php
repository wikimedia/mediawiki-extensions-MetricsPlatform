<?php

namespace MediaWiki\Extension\MetricsPlatform;

use MediaWiki\Extension\MetricsPlatform\XLab\ConfigsFetcher;
use MediaWiki\MediaWikiServices;

class Services {
	public static function getConfigsFetcher(): ConfigsFetcher {
		return MediaWikiServices::getInstance()->getService( 'MetricsPlatform.XLab.ConfigsFetcher' );
	}
}
