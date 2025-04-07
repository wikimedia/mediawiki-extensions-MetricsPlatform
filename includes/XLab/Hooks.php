<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\User\CentralId\CentralIdLookup;
use Skin;

class Hooks implements BeforePageDisplayHook {
	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableExperiments',
		'MetricsPlatformEnableExperimentOverrides',
	];

	private InstrumentConfigsFetcher $configsFetcher;
	private ExperimentManagerFactory $experimentManagerFactory;
	private CentralIdLookup $centralIdLookup;
	private ServiceOptions $options;

	public static function newInstance(
		Config $config,
		InstrumentConfigsFetcher $configsFetcher,
		ExperimentManagerFactory $experimentManagerFactory,
		CentralIdLookup $centralIdLookup
	): self {
		return new self(
			new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config ),
			$configsFetcher,
			$experimentManagerFactory,
			$centralIdLookup
		);
	}

	public function __construct(
		ServiceOptions $options,
		InstrumentConfigsFetcher $configsFetcher,
		ExperimentManagerFactory $experimentManagerFactory,
		CentralIdLookup $centralIdLookup
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->configsFetcher = $configsFetcher;
		$this->experimentManagerFactory = $experimentManagerFactory;
		$this->centralIdLookup = $centralIdLookup;
	}

	/**
	 * This hook adds a JavaScript configuration variable to the output.
	 *
	 * In order to provide experiment enrollment data with bucketing assignments
	 * for a logged-in user, we take the user's id to deterministically sample and
	 * bucket the user. Based on the sample rates of active experiments, the user's
	 * participation in experimentation cohorts is written to a configuration variable
	 * that will be read by the Metrics Platform client libraries and instrument code
	 * to send that data along during events submission.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		// Skip if:
		//
		// 1. Experiments are disabled; or
		// 2. The user is not logged in or is a temporary user.
		if (
			!$this->options->get( 'MetricsPlatformEnableExperiments' ) ||
			!$out->getUser()->isNamed()
		) {
			return;
		}

		$userId = $this->centralIdLookup->centralIdFromLocalUser( $out->getUser() );
		if ( $userId === 0 ) {
			return;
		}

		$experimentManager = $this->experimentManagerFactory->newInstance();

		// Enroll the current user into active experiments.
		// Sets the experiment config in PHP for the user's experiment enrollment data.
		$experimentManager->enrollUser( $out->getUser(), $out->getRequest() );

		// Set the JS config variable for the user's experiment enrollment data.
		$out->addJsConfigVars(
			'wgMetricsPlatformUserExperiments',
			$experimentManager->getExperimentEnrollments()
		);

		// The `ext.xLab` module contains the JS xLab SDK that is the API the feature code will use to get
		// the experiments and the corresponding assigned group for the current user
		//
		// The `ext.xLab` module also contains some QA-related functions. Those functions are sent to the
		// browser when we allow experiment enrollment overrides via `MetricsPlatformEnableExperimentOverrides`
		$out->addModules( 'ext.xLab' );
	}
}
