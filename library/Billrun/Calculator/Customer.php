<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * Billing customer calculator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
require_once __DIR__ . '/../../../application/golan/' . 'subscriber.php';

class Billrun_Calculator_Customer extends Billrun_Calculator {
	
	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = "Customer";
	
	/**
	 * method to receive the lines the calculator should take care
	 * 
	 * @return Mongodloid_Cursor Mongo cursor for iteration
	 */
	protected function getLines() {
		$lines = Billrun_Factory::db()->linesCollection();

		return $lines->query(array(
					'source' => 'ilds',
					'$or' => array(
						array('account_id' => array('$exists' => false)),
						array('subscriber_id' => array('$exists' => false))
					)
		));
	}

	/**
	 * @param int $subscriber_id the subscriber id to update
	 * @param Mongodloid_Entity $line the billing line to update
	 *
	 * @return boolean true on success else false
	 */
	protected function updateRow($row) {
		if ($row['source'] == 'api' && $row['type'] == 'refund') {
			$time = date("YmtHis", $row->get('unified_record_time')->sec);
			$phone_number = $row->get('NDC_SN');
		} else {
			$time = $row->get('call_start_dt');
			$phone_number = $row->get('caller_phone_no');
		}

		// load subscriber
		$subscriber = golan_subscriber::get($phone_number, $time);
		if (!$subscriber) {
			Billrun_Factory::log()->log("subscriber not found. phone:" . $phone_number . " time: " . $time, Zend_Log::INFO);
			return false;
		}
		
		$current = $row->getRawData();
		$added_values = array();
		
		if (!isset($subscriber['id']) || !isset($subscriber['account_id'])) {
			Billrun_Factory::log()->log("subscriber or account_id not found. phone:" . $phone_number . " time: " . $time, Zend_Log::WARN);
			return false;
		}
		
		$added_values['subscriber_id'] = $subscriber['id'];
		$added_values['account_id'] = $subscriber['account_id'];

		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);
		return true;
	}

	/**
	 * Execute the calculation process
	 */
	public function calc() {
		foreach ($this->data as $item) {
			// update billing line with billrun stamp
			if (!$this->updateRow($item)) {
				Billrun_Factory::log()->log("phone number:" .$item->get('caller_phone_no'). " cannot update billing line", Zend_Log::INFO);
				continue;
			}
		}
	}
	
	/**
	 * Execute write down the calculation output
	 */
	public function write() {
		Billrun_Factory::dispatcher()->trigger('beforeCalculatorWriteData', array('data' => $this->data));
		$lines = Billrun_Factory::db()->linesCollection();
		foreach ($this->data as $item) {
			$item->save($lines);
		}
		Billrun_Factory::dispatcher()->trigger('afterCalculatorWriteData', array('data' => $this->data));
	}
}