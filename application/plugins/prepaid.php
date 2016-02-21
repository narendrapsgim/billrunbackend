<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Prepaid plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    4.0
 */
class prepaidPlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'prepaid';
	
	/**
	 * Archive DB
	 * @var Billrun_Db
	 */
	protected $db;


	public function __construct() {
		$calculators = Billrun_Factory::config()->getConfigValue('queue.calculators', array());
		if (in_array('unify', $calculators)) {
			$this->db = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('archive.db', array()));
		} else {
			$this->db = Billrun_Factory::db(Billrun_Factory::config()->getConfigValue('db', array()));
		}
	}
	
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
		if ($calculator->getType() == 'pricing') {
			if ($this->isRebalanceRequired($row)) {
				$this->rebalance($row);
			}
		}
		
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

	public function beforeSubscriberRebalance($lineToRebalance, $balance, &$rebalanceUsagev, &$rebalanceCost, &$lineUpdateQuery) {
		try {
			if ($balance && $balance['charging_by_usaget'] == 'total_cost' || $balance['charging_by_usaget'] == 'cost') {
				$lineUpdateQuery['$inc']['balance_after'] = $rebalanceCost;
			} else {
				$lineUpdateQuery['$inc']['balance_after'] = $rebalanceUsagev;
			}
		} catch (Exception $ex) {
			Billrun_Factory::log('prepaid plugin beforeSubscriberRebalance error', Zend_Log::ERR);
			Billrun_Factory::log($ex->getCode() . ': ' . $ex->getMessage(), Zend_Log::ERR);
		}
	}

	public function afterCalculatorUpdateRow($row, Billrun_Calculator $calculator) {
//		if ($calculator->getType() == 'pricing') {
//			if ($this->isRebalanceRequired($row)) {
//				$this->rebalance($row);
//			}
//		}
	}

	protected function isRebalanceRequired($row) {
		return ($row['type'] == 'gy' && in_array($row['record_type'], array('final_request', 'update_request')) && (!isset($row['in_data_slowness']) || !$row['in_data_slowness'])) || 
			($row['type'] == 'callrt' && in_array($row['api_name'], array('release_call')));
	}

	/**
	 * Calculate balance leftovers and add it to the current balance (if were taken due to prepaid mechanism)
	 */
	protected function rebalance($row) {
		$lineToRebalance = $this->getLineToUpdate($row)->current();
		$realUsagev = $this->getRealUsagev($row);
		$chargedUsagev = $this->getChargedUsagev($row, $lineToRebalance);
		if ($chargedUsagev !== null) {
			if (($realUsagev - $chargedUsagev) < 0) {
				$this->handleRebalanceRequired($realUsagev - $chargedUsagev, $lineToRebalance);
			}
		}
	}

	/**
	 * Gets the Line that needs to be updated (on rebalance)
	 */
	protected function getLineToUpdate($row) {
		$lines_archive_coll = $this->db->archiveCollection();
		if ($row['type'] == 'gy') {
			$findQuery = array(
				"sid" => $row['sid'],
				"session_id" => $row['session_id'],
				"request_num" => array('$lt' => $row['request_num'])
			);

			$line = $lines_archive_coll->query($findQuery)->cursor()->sort(array('request_num' => -1))->limit(1);
			return $line;
		} else if ($row['type'] == 'callrt' && $row['api_name'] == 'release_call') {
			$findQuery = array(
				"sid" => $row['sid'],
				"call_reference" => $row['call_reference'],
				"api_name" => array('$ne' => "release_call"),
			);
			$line = $lines_archive_coll->query($findQuery)->cursor()->sort(array('_id' => -1))->limit(1);
			return $line;
		}
	}

	/**
	 * Gets the real usagev of the user (known only on the next API call)
	 * Given in 10th of a second
	 * 
	 * @return type
	 */
	protected function getRealUsagev($row) {
		if ($row['type'] == 'gy') {
			$sum = 0;
			foreach ($row['mscc_data'] as $msccData) {
				if (isset($msccData['used_units'])) {
					$sum += intval($msccData['used_units']);
				}
			}
			return $sum;
		} else if ($row['type'] == 'callrt' && $row['api_name'] == 'release_call') {
			$duration = (!empty($row['duration']) ? $row['duration'] : 0);
			return $duration / 10;
		}
	}

	/**
	 * Gets the amount of usagev that was charged
	 * 
	 * @return type
	 */
	protected function getChargedUsagev($row, $lineToRebalance) {
		if ($row['type'] == 'callrt' && $row['api_name'] == 'release_call') {
			$lines_archive_coll = $this->db->archiveCollection();
			$query = $this->getRebalanceQuery($row);
			$line = $lines_archive_coll->aggregate($query)->current();
			return $line['sum'];
		}
		return $lineToRebalance['usagev'];
	}

	/**
	 * In case balance is in over charge (due to prepaid mechanism), 
	 * adds a refund row to the balance.
	 * 
	 * @param type $rebalanceUsagev amount of balance (usagev) to return to the balance
	 */
	protected function handleRebalanceRequired($rebalanceUsagev, $lineToRebalance = null) {
		$call_offset = isset($lineToRebalance['call_offset']) ? $lineToRebalance['call_offset'] : 0;
		$rebalance_offset = $call_offset + $rebalanceUsagev;
		$rate = Billrun_Factory::db()->ratesCollection()->getRef($lineToRebalance->get('arate', true));
		$usaget = $lineToRebalance['usaget'];
		if (empty($lineToRebalance['in_data_slowness'])) {
			$rebalanceCost = Billrun_Calculator_CustomerPricing::getPriceByRate($rate, $usaget, $rebalanceUsagev, $lineToRebalance['plan'], $rebalance_offset);
		} else {
			$rebalanceCost = 0;
		}
		// Update subscribers balance
		$balanceRef = $lineToRebalance->get('balance_ref');
		if ($balanceRef) {
			$balances_coll = Billrun_Factory::db()->balancesCollection();
			$balance = $balances_coll->getRef($balanceRef);
			if (is_array($balance['tx']) && empty($balance['tx'])) {
				$balance['tx'] = new stdClass();
			}
			$balance->collection($balances_coll);
			if (!is_null($balance->get('balance.totals.' . $usaget . '.usagev'))) {
				$balance['balance.totals.' . $usaget . '.usagev'] += $rebalanceUsagev;
			} else if (!is_null($balance->get('balance.totals.' . $usaget . '.cost'))) {
				$balance['balance.totals.' . $usaget . '.cost'] += $rebalanceCost;
			} else {
				$balance['balance.cost'] += $rebalanceCost;
			}
		$updateQuery = $this->getUpdateLineUpdateQuery($rebalanceUsagev, $rebalanceCost);
		$this->beforeSubscriberRebalance($lineToRebalance, $balance, $rebalanceUsagev, $rebalanceCost, $updateQuery);
		} else {
			$balance = null;
		}
	//		Billrun_Factory::dispatcher()->trigger('beforeSubscriberRebalance', array());

		if ($balance) {
			$balance->save();
		} else {
			$rebalanceCost = 0;
		}

		
		// Update line in archive
		$lines_archive_coll = $this->db->archiveCollection();
		$lines_archive_coll->update(array('_id' => $lineToRebalance->getId()->getMongoId()), $updateQuery);
		
		// Update line in Lines collection
		$calculators = Billrun_Factory::config()->getConfigValue('queue.calculators', array());
		if (in_array('unify', $calculators)) {
			$sessionIdFields = Billrun_Factory::config()->getConfigValue('session_id_field', array());
			$sessionQuery = array_intersect_key($lineToRebalance->getRawData(), array_flip($sessionIdFields[$lineToRebalance['type']]));
			$findQuery = array_merge(array("sid" => $lineToRebalance['sid']), $sessionQuery);
			$lines_coll = Billrun_Factory::db()->linesCollection();
			$lines_coll->update($findQuery, $updateQuery);
		}

//		Billrun_Factory::dispatcher()->trigger('afterSubscriberRebalance', array($lineToRebalance, $balance, &$rebalanceUsagev, &$rebalanceCost, &$updateQuery));
	}

	/**
	 * Gets the update query to update subscriber's Line
	 * 
	 * @param type $rebalanceUsagev
	 */
	protected function getUpdateLineUpdateQuery($rebalanceUsagev, $cost) {
		return array(
			'$inc' => array(
				'usagev' => $rebalanceUsagev,
				'aprice' => $cost,
			),
			'$set' => array(
				'rebalance_usagev' => $rebalanceUsagev,
				'rebalance_cost' => $cost,
			)
		);
	}

	/**
	 * Gets a query to find amount of balance (usagev) calculated for a prepaid call
	 * 
	 * @return array
	 */
	protected function getRebalanceQuery($lineToRebalance) {
		if ($lineToRebalance['type'] == 'callrt' && $lineToRebalance['api_name'] == 'release_call') {
			return array(
				array(
					'$match' => array(
						"sid" => $lineToRebalance['sid'],
						"call_reference" => $lineToRebalance['call_reference']
					)
				),
				array(
					'$group' => array(
						'_id' => '$call_reference',
						'sum' => array('$sum' => '$usagev')
					)
				)
			);
		}
	}

}
