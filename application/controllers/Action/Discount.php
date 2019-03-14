<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

require_once dirname(__FILE__).'/Api.php';

/**
 * Balance action class
 *
 * @package  Action
 * @since    0.5
 * @deprecated since version 5
 */
class DiscountAction extends ApiAction {

	public function execute() {
		$request = $this->getRequest();
		$accountJson = json_decode($request->get("data"),TRUE);
		$aid = $request->get("aid");
		$action = $request->get("action");
		
		$actualAid = empty($aid) ? (empty($accountJson['aid']) ? -1 : $accountJson['aid']) : $aid;
		Billrun_Factory::log()->log("Execute discount api call to " . $actualAid, Zend_Log::INFO);
		
		$options = [
			'fake_cycle' => true,
			'generate_pdf' => false,
			'output' => 'discounts',
			'aid' => $actualAid,
		];
		
		switch ($action) {
			case 'remove':
				$ret = $this->removeDiscountsFromAccount($accountJson);
				return $ret == false 
					? $this->setError($ret, $request->getRequest())
					: $this->setSuccess($ret, $request->getRequest());
			case 'query':
				$options['eligible_only'] = false;
				break;
			case 'query_eligible':
			default:
				$options['eligible_only'] = true;
		}
		
		$this->forward('generateExpected', $options);
		return false;
	}
	
	/**
	 * remove discount lines by stamps received
	 * 
	 * @param type $params
	 */
	protected function removeDiscountsFromAccount($data) {
		if (!is_array($data)) {
			return FALSE;
		}
		foreach ($data as $stamp) {
			if (!(gettype($stamp) == 'string' && strlen($stamp) == 32)) {
				return FALSE;
			}
		}

		Billrun_Discount::remove($data);
		return $data;
	}

}
