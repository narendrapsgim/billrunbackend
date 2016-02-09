<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * PL plugin
 *
 * @package  Application
 * @subpackage Plugins
 * @since    4.0
 */
class pelephonePlugin extends Billrun_Plugin_BillrunPluginBase {

	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'pelephone';

	/**
	 * billing row to handle
	 * use to pre-fetch the billing line if the line is not passed in the requested event
	 * 
	 * @var array
	 */
	protected $row;

	public function extendRateParamsQuery(&$query, &$row, &$calculator) {
		if (!in_array($row['usaget'], array('call', 'video_call', 'sms', 'mms'))) {
			return;
		}
		$current_time = date('His');
		$weektime = date('w') . '-' . $current_time;
		$current_datetime = $row['urt']->sec;
		$day_type = Billrun_HebrewCal::getDayType($current_datetime);
		if (
			($weektime >= '5-160000' && $weektime <= '6-200000') ||
			($day_type == HEBCAL_SHORTDAY && $current_time >= '160000' && $current_time <= '235959') ||
			(
			$day_type == HEBCAL_HOLIDAY &&
			(
			($current_time >= '000000' && $current_time <= '195959') ||
			(Billrun_HebrewCal::getDayType($nextday = strtotime('+1 day', $current_datetime)) == HEBCAL_HOLIDAY || date('w', $nextday) == 6)
			)
			)
		) {
			$shabbat = true;
		} else {
			$shabbat = false;
		}
		if ($this->isInterconnect($row)) {
			$interconnect = true;
		} else {
			$interconnect = false;
		}
		$query[0]['$match']['params.shabbat'] = $shabbat;
		$query[0]['$match']['params.interconnect'] = $interconnect;
	}

	protected function canSubscriberEnterDataSlowness($row) {
		return isset($row['subscriber_soc']) && !empty($row['subscriber_soc']);
	}

	protected function isSubscriberInDataSlowness($row) {
		return isset($row['in_data_slowness']) && $row['in_data_slowness'];
	}

	/**
	 * Gets data slowness speed and SOC according to plan or default 
	 * 
	 * @return int slowness speed in Kb/s and SOC code
	 * @todo Check if plan has a value for slowness
	 */
	protected function getDataSlownessParams($row) {
		// TODO: Check first if it's set in plan
		$slownessParams = Billrun_Factory::config()->getConfigValue('realtimeevent.data.slowness');
		$socKey = $row['subscriber_soc'];
		if (!isset($slownessParams[$socKey])) {
			$socKey = "DEFAULT";
		}
		return array(
			'speed' => $slownessParams[$socKey]['speed'],
			'soc' => $slownessParams[$socKey]['SOC'],
			'command' => $slownessParams['command'],
			'applicationId' => $slownessParams['applicationId'],
			'requestUrl' => $slownessParams['requestUrl'],
		);
	}

	public function afterSubscriberBalanceNotFound(&$row) {
		if ($row['type'] === 'gy') {
			if ($this->isSubscriberInDataSlowness($row)) {
				$row['usagev'] = Billrun_Factory::config()->getConfigValue('realtimeevent.data.quotaDefaultValue', 10 * 1024 * 1024);
			} else if ($this->canSubscriberEnterDataSlowness($row)) {
				$this->enterSubscriberToDataSlowness($row);
				$row['usagev'] = Billrun_Factory::config()->getConfigValue('realtimeevent.data.quotaDefaultValue', 10 * 1024 * 1024);
			}
		}
	}

	protected function enterSubscriberToDataSlowness($row) {
		// Update subscriber in DB
		$subscribersColl = Billrun_Factory::db()->subscribersCollection();
		$findQuery = array_merge(Billrun_Util::getDateBoundQuery(), array('sid' => $row['sid']));
		$updateQuery = array('$set' => array('in_data_slowness' => true));
		$subscribersColl->update($findQuery, $updateQuery);

		//Send request to slowdown the subscriber
		$encoder = new Billrun_Encoder_Xml();
		$slownessParams = $this->getDataSlownessParams($row);
		$requestBody = array(
			'HEADER' => array(
				'APPLICATION_ID' => $slownessParams['applicationId'],
				'COMMAND' => $slownessParams['command'],
			),
			'PARAMS' => array(
				'MSISDN' => $row['msisdn'],
				'SLOWDOWN_SPEED' => $slownessParams['speed'],
				'SOC' => $slownessParams['soc'],
			)
		);
		$request = array($encoder->encode($requestBody, "REQUEST"));
		$requestUrl = $slownessParams['requestUrl'];
		Billrun_Util::sendRequest($requestUrl, $request);
	}

	/**
	 * method to check if billing row is interconnect (not under PL network)
	 * 
	 * @param array $row the row to check
	 * 
	 * @return boolean true if not under PL network else false
	 */
	protected function isInterconnect($row) {
		return isset($row['np_code']) && substr($row['np_code'], 0, 3) != '831'; // 831 np prefix of PL; @todo: move it to configuration
	}

	/**
	 * use to store the row to extend balance query (method extendGetBalanceQuery)
	 * 
	 * @param array $row
	 * @param Billrun_Calculator $calculator
	 */
	public function beforeCalculatorUpdateRow(&$row, Billrun_Calculator $calculator) {
		if ($calculator->getType() == 'pricing') {
			$this->row = $row;
		}
	}

	/**
	 * method to extend the balance
	 * 
	 * @param array $query the query that will pull the balance
	 * @param int $timeNow the time of the row (unix timestamp)
	 * @param string $chargingType
	 * @param string $usageType
	 * @param Billrun_Balance $balance
	 * 
	 * @todo change the values to be with flag taken from pp_includes into balance object
	 * 
	 */
	public function extendGetBalanceQuery(&$query, &$timeNow, &$chargingType, &$usageType, Billrun_Balance $balance) {
		if (!empty($this->row)) {
			$pp_includes_external_ids = array();
			if ($this->isInterconnect($this->row)) {
				// we are out of PL network
				array_push($pp_includes_external_ids, 7);
			}

			if (isset($this->row['call_type']) && $this->row['call_type'] == '2') {
				array_push($pp_includes_external_ids, 3, 4);
			}

			if (count($pp_includes_external_ids)) {
				$query['pp_includes_external_id'] = array('$nin' => $pp_includes_external_ids);
			}
		}
	}

	/**
	 * 
	 * @param Mongodloid_Entity $record
	 * @param Billrun_ActionManagers_Subscribers_Update $updateAction
	 */
	public function beforeSubscriberSave(&$record, Billrun_ActionManagers_Subscribers_Update $updateAction) {
		if (isset($record['subscriber_soc']) && empty($record['subscriber_soc'])) {
			$record['in_data_slowness'] = FALSE;
		}
	}

}
