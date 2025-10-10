<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentAuthority;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentRequest;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Logger\LoggerFactory;
use Wikimedia\Assert\Assert;

class Hooks implements
	AuthPreserveQueryParamsHook,
	BeforeInitializeHook
{
	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableExperiments',
		'MetricsPlatformEnableExperimentConfigsFetching',
	];

	public function __construct(
		private readonly Config $config,
		private readonly InstrumentConfigsFetcher $configsFetcher,
		private readonly EnrollmentAuthority $enrollmentAuthority,
		private readonly ExperimentManager $experimentManager,
	) {
		Assert::parameter(
			$config->has( 'MetricsPlatformEnableExperiments' ),
			'$config',
			'Required config "MetricsPlatformEnableExperiments" missing.'
		);
		Assert::parameter(
			$config->has( 'MetricsPlatformEnableExperimentConfigsFetching' ),
			'$config',
			'Required config "MetricsPlatformEnableExperimentConfigsFetching" missing.'
		);
	}

	public function onAuthPreserveQueryParams( array &$params, array $options ) {
		$request = RequestContext::getMain()->getRequest();
		$mpo = $request->getRawVal( 'mpo' );
		if ( $mpo ) {
			$params['mpo'] = $mpo;
			return;
		}
		$experiments = $this->config->get( 'MetricsPlatformAuthPreserveQueryParamsExperiments' );
		$mpoParams = [];
		foreach ( $experiments as $experimentName ) {
			$experiment = $this->experimentManager->getExperiment( $experimentName );
			$assignedGroup = $experiment?->getAssignedGroup();
			if ( $assignedGroup ) {
				$mpoParams[] = "$experimentName:$assignedGroup";
			}
		}
		if ( $mpoParams ) {
			$params['mpo'] = implode( ';', $mpoParams );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize( $title, $unused, $output, $user, $request, $mediaWikiEntryPoint ) {
		if ( !$this->config->get( 'MetricsPlatformEnableExperiments' ) ) {
			return;
		}

		$activeLoggedInExperiments = [];

		// Optimization: Only get experiment configs from the InstrumentConfigsFetcher's backing store if the user is
		// registered.
		if ( $user->isRegistered() ) {
			if ( $this->config->get( 'MetricsPlatformEnableExperimentConfigsFetching' ) ) {
				$this->configsFetcher->updateExperimentConfigs();
			}

			$activeLoggedInExperiments = $this->config->has( 'MetricsPlatformExperiments' ) ?
				$this->config->get( 'MetricsPlatformExperiments' ) :
				$this->configsFetcher->getExperimentConfigs();
		}

		$enrollmentRequest = new EnrollmentRequest( $activeLoggedInExperiments, $user, $request );
		$result = new EnrollmentResultBuilder();

		$this->enrollmentAuthority->enrollUser( $enrollmentRequest, $result );

		// Initialize the PHP xLab SDK
		$this->experimentManager->initialize( $result->build() );

		// Initialize the JS xLab SDK
		$output->addJsConfigVars(
			'wgMetricsPlatformUserExperiments',
			$result->build()
		);

		// Note well that the JS xLab SDK will always be added to the output. This allows developers to implement and
		// deploy their experiments before they are activated in xLab without error. It also allows us to handle
		// transient network failures or xLab API errors gracefully.
		$output->addModules( 'ext.xLab' );

		// T393101: Add CSS classes representing experiment enrollment and assignment automatically so that experiment
		// implementers don't have to do this themselves.
		$output->addBodyClasses( EnrollmentCssClassSerializer::serialize( $result->build() ) );

		// T404262: Add field for A/B test (and control) findability in Logstash
		LoggerFactory::getContext()->add( [ 'context.ab_tests' => $result->getEnrollmentsWithoutSubjectIds() ] );
	}
}
