<?php

/**
 * Dcb Soap Handler Class 
 * 
 * 
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero Public License version 3 or later; see LICENSE.txt
 */

/**
 * Dcb_Soap_Handler Class Definition
 */
class Dcb_Soap_Handler {

	const GOOGLE_RESULT_CODE_SUCCESS = 'SUCCESS';
	const GOOGLE_RESULT_CODE_GENERAL_FAILURE = 'GENERAL_FAILURE';
	const GOOGLE_RESULT_CODE_RETRIABLE_ERROR = 'RETRIABLE_ERROR';
	const GOOGLE_RESULT_CODE_INVALID_USER = 'INVALID_USER';
	const GOOGLE_RESULT_CODE_NO_LONGER_PROVISIONED = 'NO_LONGER_PROVISIONED';
	const GOOGLE_RESULT_CODE_INVALID_CURRENCY = 'INVALID_CURRENCY';

	/**
	 * The subscriber associated with the request
	 * @var Billrun_Subscriber
	 */
	protected $subscriber;

	/**
	 *
	 * @var array
	 */
	protected $config;

	public function __construct() {
		$this->config = Billrun_Factory::config()->getConfigValue('dcb.google');
		$this->subscriber = Billrun_Factory::subscriber();
	}

	public function __call($name, $arguments) {
		if (method_exists($this, 'do' . $name)) {
			return call_user_func(array($this, 'do' . $name), $arguments[0]);
		} else {
			return false;
		}
	}

	public function doEcho($request) {
		$response = new stdclass;
		$response->Version = $request->Version;
		$response->CorrelationId = $request->CorrelationId;
		$response->Result = self::GOOGLE_RESULT_CODE_SUCCESS;
		$response->OriginalMessage = $request->Message;
		return $response;
	}

	public function GetProvisioning($request) {
		$response = new stdclass;
		$response->Version = $request->Version;
		$response->CorrelationId = $request->CorrelationId;
		$sid = $this->getSid($request->UserIdentifier->OperatorUserToken);
		if ($sid) {
			$identityParams = $this->getIdentityParams($sid);
			$this->subscriber->load($identityParams);
			if (!$this->subscriber->isValid()) {
				$response->Result = self::GOOGLE_RESULT_CODE_INVALID_USER;
			} else {
				$response->Result = self::GOOGLE_RESULT_CODE_SUCCESS;
				if ($this->isDcbProvisioned($this->subscriber)) {
					$response->IsProvisioned = TRUE;
					$response->SubscriberCurrency = $this->config['currency'];
					$response->TransactionLimit = intval($this->config['transaction_limit']);
					$response->AccountType = $this->config['account_type'];
				} else {
					$response->IsProvisioned = FALSE;
				}
			}
		} else {
			$response->Result = self::GOOGLE_RESULT_CODE_INVALID_USER;
		}
		return $response;
	}

	public function Auth($request) {
		$response = new stdclass;
		$response->Version = $request->Version;
		$response->CorrelationId = $request->CorrelationId;
		$sid = $this->getSid($request->OperatorUserToken);
		if ($request->Currency != $this->config['currency']) {
			$response->Result = self::GOOGLE_RESULT_CODE_INVALID_CURRENCY;
		} else if ($sid) {
			$identityParams = $this->getIdentityParams($sid);
			$this->subscriber->load($identityParams);
			if (!$this->subscriber->isValid()) {
				$response->Result = self::GOOGLE_RESULT_CODE_INVALID_USER;
			} else {
				if ($this->isDcbProvisioned($this->subscriber)) {
					$response->Result = self::GOOGLE_RESULT_CODE_SUCCESS;
				} else {
					$response->Result = self::GOOGLE_RESULT_CODE_NO_LONGER_PROVISIONED;
				}
			}
		} else {
			$response->Result = self::GOOGLE_RESULT_CODE_INVALID_USER;
		}
		return $response;
	}

	/**
	 * Indicates if the subscriber is provisioned for Dcb
	 * @param Billrun_Subscriber $subscriber
	 */
	protected function isDcbProvisioned($subscriber) {
		return $subscriber->isDcbActive() && !$subscriber->isInDebt();
	}

	protected function getSid($OUT) {
		$cursor = Billrun_Factory::db()->tokensCollection()->query(array('OUT' => $OUT))->cursor();
		if (!$cursor->count()) {
			return null;
		} else {
			return $cursor->current()['sid'];
		}
	}

	protected function getIdentityParams($sid) {
		return array(
			'sid' => $sid,
			'DATETIME' => date(Billrun_Base::base_dateformat),
		);
	}

}

