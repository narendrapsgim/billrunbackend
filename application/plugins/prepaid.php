<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Prepaid plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    0.5
 */
class prepaidPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'prepaid';
	
	/**
	 * Method to trigger api outside of Billrun.
	 * afterSubscriberBalanceNotFound trigger after the subscriber has no available balance (relevant only for prepaid subscribers)
	 * 
	 * @param array $row the line from lines collection
	 * 
	 * @return boolean true for success, false otherwise
	 * 
	 */
	public function afterSubscriberBalanceNotFound($row) {
		return false; // TODO: temporary, disable send of clear call
		return self::sendClearCallRequest($row);
	}
	
	/**
	 * method to set call_offset
	 * 
	 * @param array $row the row we are running on
	 * @param Billrun_Calculator $calculator the calculator trigger the event
	 * 
	 * @return void
	 */
	public function beforeCalculatorUpdateRow(&$row, Billrun_Calculator $calculator) {
		if (!isset($row['call_offset']) && ($calculator->getType() == 'pricing' || stripos(get_class($calculator), 'rate') !== FALSE)) {
			$row['call_offset'] = $this->getRowCurrentUsagev($row);
		}
		
	}
	
	protected function getRowCurrentUsagev($row) {
		try {
			$lines_coll = Billrun_Factory::db()->linesCollection();
			$query = $this->getRowCurrentUsagevQuery($row);
			$line = $lines_coll->aggregate($query)->current();
		} catch (Exception $ex) {
			Billrun_Factory::log($ex->getCode() . ': ' . $ex->getMessage());
		}
		return isset($line['sum']) ? $line['sum'] : 0;
	}
	
	protected function getRowCurrentUsagevQuery($row) {
		$query = array(
			array(
				'$match' => array(
					"sid" => $row['sid'],
				)
			),
			array(
				'$group' => array(
					'_id' => null,
					'sum' => array('$sum' => '$usagev'),
				)
			)
		);
		if ($row['usaget'] == 'call') {
			$query[0]['$match']['call_reference'] = $row['call_reference'];
			$query[0]['$match']['api_name'] = array('$ne' => 'start_call');
			$query[0]['$match']['stamp'] = array('$ne' => $row['stamp']);
		} else {
			$query[0]['$match']['session_id'] = $row['session_id'];
		}
		return $query;
	}

	
	/**
	 * Send a request of ClearCall
	 * 
	 * @param type $row
	 * @return boolean true for success, false otherwise
	 */
	protected static function sendClearCallRequest($row) {
		$encoder = Billrun_Encoder_Manager::getEncoder(array(
			'usaget' => $row['usaget']
		));
		if (!$encoder) {
			Billrun_Factory::log('Cannot get encoder', Zend_Log::ALERT);
			return false;
		}
		
		$row['record_type'] = 'clear_call';
		$responder = Billrun_ActionManagers_Realtime_Responder_Manager::getResponder($row);
		if (!$responder) {
			Billrun_Factory::log('Cannot get responder', Zend_Log::ALERT);
			return false;
		}

		$request = array($encoder->encode($responder->getResponse(), "request"));
		// Sends request
		$requestUrl = Billrun_Factory::config()->getConfigValue('IN.request.url.realtimeevent');
		return Billrun_Util::sendRequest($requestUrl, $request);
	}
	
	public function afterUpdateSubscriberBalance($row, $balance, &$pricingData, $calculator) {
		try {
			$pp_includes_name = $balance->get('pp_includes_name');
			if (!empty($pp_includes_name)) {
				$pricingData['pp_includes_name'] = $pp_includes_name;
			}
			$pp_includes_external_id = $balance->get('pp_includes_external_id');
			if (!empty($pp_includes_external_id)) {
				$pricingData['pp_includes_external_id'] = $pp_includes_external_id;
			}

			$balance_before = $this->getBalanceValue($balance);
			$balance_usage = $this->getBalanceUsage($balance, $row);
			$pricingData["balance_before"] = $balance_before;
			$pricingData["balance_after"] = $balance_before + $balance_usage;
			$pricingData["usage_unit"] = $balance->get('charging_by_usaget_unit');
		} catch (Exception $ex) {
			Billrun_Factory::log('prepaid plugin afterUpdateSubscriberBalance error', Zend_Log::ERR);
			Billrun_Factory::log($ex->getCode() . ': ' . $ex->getMessage(), Zend_Log::ERR);
		}
	}
	
	protected function getBalanceValue($balance) {
		$charging_by_usaget = $balance->get('charging_by_usaget');
		if ($charging_by_usaget == 'total_cost' || $charging_by_usaget == 'cost') {
			return $balance->get('balance')['cost'];
		}
		$charging_by = $balance->get('charging_by');
		return $balance->get('balance')['totals'][$charging_by_usaget][$charging_by];
	}
	
	protected function getBalanceUsage($balance, $row) {
		$charging_by_usaget = $balance->get('charging_by_usaget');
		$charging_by = $balance->get('charging_by');
		if ($charging_by_usaget == 'total_cost' || $charging_by_usaget == 'cost' || $charging_by == 'cost' || $charging_by == 'total_cost') {
			return $row['aprice'];
		}
		return $row['usagev'];
	}
	
	public function beforeSubscriberRebalance($lineToRebalance, $balance, &$rebalanceUsagev, &$rebalanceCost, &$lineUpdateQuery, $responder) {
		try {
			if ($balance['charging_by_usaget'] == 'total_cost' || $balance['charging_by_usaget'] == 'cost') {
				$lineUpdateQuery['$inc']['balance_after'] = $rebalanceCost;
			} else {
				$lineUpdateQuery['$inc']['balance_after'] = $rebalanceUsagev;
			}
		} catch (Exception $ex) {
			Billrun_Factory::log('prepaid plugin beforeSubscriberRebalance error', Zend_Log::ERR);
			Billrun_Factory::log($ex->getCode() . ': ' . $ex->getMessage(), Zend_Log::ERR);
		}

	}

}
