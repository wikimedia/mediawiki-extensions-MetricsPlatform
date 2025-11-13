<?php

namespace MediaWiki\Extension\MetricsPlatform\XLab;

use MediaWiki\Api\Hook\APIAfterExecuteHook;
use MediaWiki\Auth\Hook\AuthPreserveQueryParamsHook;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\MetricsPlatform\InstrumentConfigsFetcher;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentAuthority;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentRequest;
use MediaWiki\Extension\MetricsPlatform\XLab\Enrollment\EnrollmentResultBuilder;
use MediaWiki\Hook\ApiBeforeMainHook;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;

class Hooks implements
	AuthPreserveQueryParamsHook,
	BeforeInitializeHook,
	BeforePageDisplayHook,
	ApiBeforeMainHook,
	APIAfterExecuteHook
{
	public const CONSTRUCTOR_OPTIONS = [
		'MetricsPlatformEnableExperiments',
		'MetricsPlatformEnableExperimentConfigsFetching',
	];

	private ?EnrollmentResultBuilder $latestEnrollmentResult = null;

	public function __construct(
		private readonly Config $config,
		private readonly InstrumentConfigsFetcher $configsFetcher,
		private readonly EnrollmentAuthority $enrollmentAuthority,
		private readonly ExperimentManager $experimentManager,
		private readonly LoggerInterface $logger,
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
		$this->doEnrollUser( $user, $request );
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $this->checkShouldDecorateOutput() ) {

			// Initialize the JS xLab SDK
			//
			// Note well that the JS xLab SDK will always be added to the output. This allows developers to implement
			// and deploy their experiments before they are activated in xLab without error. It also allows us to handle
			// transient network failures or xLab API errors gracefully.
			$out->addJsConfigVars( 'wgMetricsPlatformUserExperiments', $this->latestEnrollmentResult->build() );
			$out->addModules( 'ext.xLab' );

			// T393101: Add CSS classes representing experiment enrollment and assignment automatically so that
			// experiment implementers don't have to do this themselves.
			$out->addBodyClasses( EnrollmentCssClassSerializer::serialize( $this->latestEnrollmentResult->build() ) );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onApiBeforeMain( &$main ) {
		$this->doEnrollUser( $main->getUser(), $main->getRequest() );
	}

	/**
	 * @inheritDoc
	 */
	public function onAPIAfterExecute( $module ) {
		if ( !$this->checkShouldDecorateOutput() ) {
			return;
		}

		$header = EnrollmentHeaderSerializer::serialize( $this->latestEnrollmentResult->build() );

		if ( $header ) {
			$module->getRequest()
				->response()
				->header( $header );
		}
	}

	/**
	 * Gathers experiment enrollment results from various
	 * [Experiment Enrollment Sampling Authorities](https://wikitech.wikimedia.org/wiki/Test_Kitchen/Sampling) and
	 * initializes the `MetricsPlatform.XLab.ExperimentManager` service with them as well as stores them to decorate
	 * the output before it is sent to the user agent.
	 *
	 * @param User $user
	 * @param WebRequest $request
	 * @return void
	 */
	private function doEnrollUser( User $user, WebRequest $request ): void {
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
		$this->latestEnrollmentResult = new EnrollmentResultBuilder();

		$this->enrollmentAuthority->enrollUser( $enrollmentRequest, $this->latestEnrollmentResult );

		// Initialize the PHP xLab SDK
		$this->experimentManager->initialize( $this->latestEnrollmentResult->build() );

		// T404262: Add field for A/B test (and control) findability in Logstash
		LoggerFactory::getContext()->add( [ 'context.ab_tests' => $this->latestEnrollmentResult->build() ] );
	}

	/**
	 * Checks whether the output should be decorated with experiment enrollment results.
	 *
	 * This method should only be called before decorating the output as it logs an error if experiment enrollment
	 * hasn't been performed.
	 *
	 * @return bool
	 */
	private function checkShouldDecorateOutput(): bool {
		if ( !$this->config->get( 'MetricsPlatformEnableExperiments' ) ) {
			return false;
		}

		if ( !$this->latestEnrollmentResult ) {
			$this->logger->error( 'Cannot decorate the output before experiment enrollment has been performed' );

			return false;
		}

		return true;
	}
}
