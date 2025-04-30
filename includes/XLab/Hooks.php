<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Skin\Skin;
use Psr\Log\LoggerInterface;

class Hooks implements BeforePageDisplayHook {
	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableExperiments',
		'MetricsPlatformEnableExperimentOverrides',
		'MetricsPlatformEnableExperimentConfigsFetching',
	];

	private InstrumentConfigsFetcher $configsFetcher;
	private ExperimentManagerFactory $experimentManagerFactory;
	private ServiceOptions $options;
	private LoggerInterface $logger;

	public static function newInstance(
		Config $config,
		InstrumentConfigsFetcher $configsFetcher,
		ExperimentManagerFactory $experimentManagerFactory
	): self {
		return new self(
			new ServiceOptions( self::CONSTRUCTOR_OPTIONS, $config ),
			$configsFetcher,
			$experimentManagerFactory
		);
	}

	public function __construct(
		ServiceOptions $options,
		InstrumentConfigsFetcher $configsFetcher,
		ExperimentManagerFactory $experimentManagerFactory
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->configsFetcher = $configsFetcher;
		$this->experimentManagerFactory = $experimentManagerFactory;
		$this->logger = MediaWikiServices::getInstance()->getService( 'MetricsPlatform.Logger' );
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
		// 1. Experiments are disabled
		if ( !$this->options->get( 'MetricsPlatformEnableExperiments' ) ) {
			return;
		}

		$experimentManager = $this->experimentManagerFactory->newInstance();

		// Set experiment enrollments for everyone (parsing the `X-Experiment-Enrollments` header)
		// and logged-in experiments (running the enrollment algorithm, `mediawiki` is the authority for
		// these experiments)
		$experimentManager->enrollUser( $out->getUser(), $out->getRequest() );
		$experimentEnrollments = $experimentManager->getExperimentEnrollments();

		// Set the JS config variable for the user's experiment enrollment data.
		$out->addJsConfigVars(
			'wgMetricsPlatformUserExperiments',
			$experimentEnrollments
		);

		// The `ext.xLab` module contains the JS xLab SDK that is the API the feature code will use to get
		// the experiments and the corresponding assigned group for the current user
		//
		// The `ext.xLab` module also contains some QA-related functions. Those functions are sent to the
		// browser when we allow experiment enrollment overrides via `MetricsPlatformEnableExperimentOverrides`
		$out->addModules( 'ext.xLab' );

		// T393101: Add CSS classes representing experiment enrollment and assignment automatically so that experiment
		// implementers don't have to do this themselves.
		$out->addBodyClasses( EnrollmentCssClassSerializer::serialize( $experimentEnrollments ) );
	}
}
