<?php

namespace MediaWiki\Extension\MetricsPlatform\Maintenance;

// @codeCoverageIgnoreStart
use MediaWiki\Config\Config;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Maintenance\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

// @phpcs
class UpdateConfigs extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'MetricsPlatform' );
		$this->addDescription(
			'Fetches instrument and experiment configs from xLab and updates the backing store if they have changed.'
		);
	}

	public function execute() {
		/** @var Config $config */
		$config = $this->getConfig();

		if ( !$config->has( 'MetricsPlatformInstrumentConfiguratorBaseUrl' ) ) {
			$this->fatalError( <<<'MSG'
$wgMetricsPlatformInstrumentConfiguratorBaseUrl is not set. Please set it to the URL of an xLab instance that is
contactable from this host.
MSG );
		}

		/** @var InstrumentConfigsFetcher $configsFetcher */
		$configsFetcher = $this->getServiceContainer()->getService( 'MetricsPlatform.ConfigsFetcher' );

		$this->output( 'Updating instrument configs...' );
		$configsFetcher->updateInstrumentConfigs();
		$this->output( "Done!\n" );

		$this->output( 'Updating experiment configs...' );
		$configsFetcher->updateExperimentConfigs();
		$this->output( "Done!\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = UpdateConfigs::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
