<?php
namespace Tx\FormhandlerSubscription\PreProcessor;

/*                                                                        *
 * This script belongs to the TYPO3 extension "formhandler_subscription". *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use Tx\Authcode\Domain\Enumeration\AuthCodeAction;
use Tx\Authcode\Domain\Enumeration\AuthCodeType;
use Tx\FormhandlerSubscription\Utils\AuthCodeUtils;
use Tx_Formhandler_PreProcessor_ValidateAuthCode as FormhandlerValidateAuthCodePreProcessor;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This processor validates the submitted auth code that was generated by
 * Tx_FormhandlerSubscription_Finisher_GenerateAuthCodeDB and executes the
 * configured action.
 *
 * There are two actions possible at the moment: enableRecord and accessForm.
 *
 * If the action was set to enableRecord the referenced record will be
 * enabled (hidden will be set to 0).
 *
 * If the action was set to accessForm the submitted auth code will be stored
 * in the session and the auth code data and the data of the referenced record
 * will be made available in the GP array.
 */
class ValidateAuthCodeDB extends FormhandlerValidateAuthCodePreProcessor {

	/**
	 * Auth code related utility functions
	 *
	 * @var AuthCodeUtils
	 */
	protected $utils;

	/**
	 * TYPO3 database
	 *
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $typo3Db;

	/**
	 * @var \Tx\Authcode\AuthCodeValidator
	 */
	protected $authCodeValidator;

	/**
	 * @var \Tx\Authcode\Domain\Repository\AuthCodeRecordRepository
	 */
	protected $authCodeRecordRepository;

	/**
	 * Inits the finisher mapping settings values to internal attributes.
	 *
	 * @param array $gp
	 * @param array $settings
	 * @return void
	 */
	public function init($gp, $settings) {

		parent::init($gp, $settings);

		$this->utils = AuthCodeUtils::getInstance();

		/** @var \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager */
		$objectManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->authCodeValidator = $objectManager->get('Tx\\Authcode\\AuthCodeValidator');
		$this->authCodeRecordRepository = $objectManager->get('Tx\\Authcode\\Domain\\Repository\\AuthCodeRecordRepository');
	}

	/**
	 * Checks the submitted auth code, executes the configured action and optionally
	 * redirects the user to a success page if the auth code is valid.
	 *
	 * If the auth code is invalid an exception will be thrown or the user will be
	 * redirected to a configured error page.
	 *
	 * @throws \Exception If the validation of the auth code fails and no error page was configured
	 * @return array
	 */
	public function process() {

		try {

			$submittedAuthCode = (string)$this->utils->getAuthCode();

			if ($submittedAuthCode === '') {
				if (!intval($this->settings['authCodeIsOptional'])) {
					$this->utilityFuncs->throwException('validateauthcode_insufficient_params');
				} else {
					return $this->gp;
				}
			}

			$authCode = $this->utils->getAuthCodeDataFromDB($submittedAuthCode);
			if (!isset($authCode)) {
				$this->utilityFuncs->throwException('validateauthcode_no_record_found');
			}

			$isAccessPageAction = $authCode->getAction() === AuthCodeAction::ACCESS_PAGE;

			if (intval($this->settings['doNotInvalidateAuthCode'])) {
				$this->authCodeValidator->setInvalidateAuthCodeAfterAccess(FALSE);
			} elseif (!isset($this->settings['doNotInvalidateAuthCode']) && $isAccessPageAction) {
				$this->utilityFuncs->debugMessage('Using auth code action "accessPage" (former "accessForm) will not automatically set "doNotInvalidateAuthCode" in future versions. You need to set this manually!', array(), 2);
				GeneralUtility::deprecationLog('formhandler_subscription: Using auth code action "accessPage" (former "accessForm) will not automatically set "doNotInvalidateAuthCode" in future versions. You need to set this manually!');
				$this->authCodeValidator->setInvalidateAuthCodeAfterAccess(FALSE);
			}

			try {
				$authCode = $this->authCodeValidator->validateAuthCodeAndExecuteAction($authCode);
			} catch (\Tx\Authcode\Exception\InvalidAuthCodeException $invalidAuthCodeException) {
				$this->utilityFuncs->throwException('validateauthcode_no_record_found');
			}

			if ($isAccessPageAction) {

				// Make the auth code available in the form so that it can be
				// submitted as a hidden field
				$this->gp['authCode'] = $submittedAuthCode;

				// Make the auth code data available so that it can be displayed to the user
				$this->gp['authCodeRecord'] = $authCode;

				if ($authCode->getType() === AuthCodeType::RECORD) {

					// Make the auth code  record data available so that it can be displayed to the user
					$authCodeRecordData = $this->authCodeRecordRepository->getAuthCodeRecordFromDB($authCode);
					$this->gp['authCodeRecord'] = $authCodeRecordData;

					if (intval($this->settings['mergeRecordDataToGP'])) {
						$this->gp = array_merge($this->gp, $authCodeRecordData);
					}
				} elseif ($authCode->getType() == AuthCodeType::INDEPENDENT) {
					if (!empty($this->settings['mergeIndependentIdentifierToGP'])) {
						$identifierMapping = (string)$this->settings['mergeIndependentIdentifierToGP'];
						$this->gp[$identifierMapping] = $authCode->getIdentifier();
					}
				}

				// Store the authCode in the session so that the user can use it
				// on different pages without the need to append it as a get
				// parameter everytime
				$this->utils->storeAuthCodeInSession($authCode->getAuthCode());
			}

			$redirectPage = $this->utilityFuncs->getSingle($this->settings, 'redirectPage');
			if ($redirectPage) {
				$this->utilityFuncs->doRedirect($redirectPage, $this->settings['correctRedirectUrl'], $this->settings['additionalParams.']);
				exit;
			}

		} catch (\Exception $e) {

			// Make sure, invalid auth codes are deleted.
			if (isset($authCode)) {
				$this->authCodeValidator->invalidateAuthCode($authCode);
			}

			$redirectPage = $this->utilityFuncs->getSingle($this->settings, 'errorRedirectPage');
			if ($redirectPage) {
				$this->utilityFuncs->doRedirect($redirectPage, $this->settings['correctRedirectUrl'], $this->settings['additionalParams.']);
				exit;
			} else {
				throw new \Exception($e->getMessage());
			}
		}

		return $this->gp;
	}
}