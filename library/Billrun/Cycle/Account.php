<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Represents an aggregatble account
 *
 * @package  Cycle
 * @since    5.2
 */
class Billrun_Cycle_Account extends Billrun_Cycle_Common {
	/**
	 * 
	 * @var Billrun_Cycle_Account_Invoice
	 */
	protected $billrun;
	
	/**
	 * Aggregate the data, store the results in the billrun container.
	 * @return array - Array of aggregated results
	 */
	public function aggregate($data = array()) {
		Billrun_Factory::log("Records to aggregate: " . count($this->records));
		$results = parent::aggregate();
		Billrun_Factory::log("Account aggregated: " . count($results));
		$this->billrun->addLines($results);
		return $results;
	}
	
	/**
	 * Write the invoice to the Billrun collection
	 * @param int $min_id minimum invoice id to start from
	 */
	public function writeInvoice($min_id) {
		$this->billrun->close($min_id);
	}
	
	/**
	 * Validate the input
	 * @param type $input
	 * @return type
	 */
	protected function validate($input) {
		// TODO: Complete
		return isset($input['subscribers']) && is_array($input['subscribers']) &&
			   isset($input['billrun']) && is_a($input['billrun'], 'Billrun_Cycle_Account_Billrun');
	}

	/**
	 * Construct the subscriber records
	 * @param type $data
	 */
	protected function constructRecords($data) {
		$this->billrun = $data['billrun'];
		$this->records = array();
		$subscribers = $data['subscribers'];
		$cycle = $data['cycle'];
		$plans = &$data['plans'];
		$services = &$data['services'];
		
		$sorted = $this->sortSubscribers($subscribers, $cycle->end());
		
		// Filter subscribers.
		$filtered = $this->constructSubscriberData($sorted, $cycle);

		$aggregatableRecords = array();
		foreach ($sorted as $sid => $subscriberList) {
			Billrun_Factory::log("Constructing records for sid " . $sid);
			
			$filteredSid = array();
			if(isset($filtered[$sid])) {
				$filteredSid = $filtered[$sid];
			} else {
				Billrun_Factory::log("SID " . $sid . " not in filtered!");
			}
			$constructed = $this->constructForSid($subscriberList, $filteredSid, $plans, $services, $cycle);
			$aggregatableRecords = array_merge($aggregatableRecords, $constructed);
		}
		Billrun_Factory::log("Constructed: " . count($aggregatableRecords));
		$this->records = $aggregatableRecords;
	}

	/**
	 * Construct the subscriber records for an sid
	 * @param array $sorted - Sorted subscribers by sid
	 * @param array $filtered - Filtered plans ans services
	 * @param array $plans - Raw plan data from the mongo
	 * @param Billrun_DataTypes_CycleTime $cycle - Current cycle time.
	 */
	protected function constructForSid($sorted, $filtered, &$plans, &$services, $cycle) {
		$aggregateable = array();
		foreach ($sorted as $sub) {
			$constructed = $sub;
			unset($constructed['plans']);
			$filterKey = $sub['sto'];
			if(isset($filtered[$filterKey])) {
				$constructed += $filtered[$filterKey]; 
			} else {
				Billrun_Factory::log("Key not in dictionary. " . $filterKey);
				$constructed['plans'] = array();
			}
			
			$constructed['mongo_plans'] = &$plans;
			$constructed['mongo_services'] = &$services;
			$constructed['cycle'] = &$cycle;
			$constructed['line_stump'] = $this->getLineStump($sub, $cycle);
			$cycleSub =  new Billrun_Cycle_Subscriber($constructed);
			$this->billrun->addSubscriber($cycleSub, $sub);
			$aggregateable[] = $cycleSub;
		}
		return $aggregateable;
	}
	
	protected function getLineStump(array $subscriber, Billrun_DataTypes_CycleTime $cycle) {
		$flatEntry = array(
			'aid' => $subscriber['aid'],
			'sid' => $subscriber['sid'],
			'source' => 'billrun',
			'billrun' => $cycle->key(),
			'type' => 'flat',
			'usaget' => 'flat',
//			'cycle' => $cycle,
			'urt' => new MongoDate($cycle->end()),
		);
		
		return $flatEntry;
	}
	
	/**
	 * 
	 * @param type $subscribers
	 * @param type $endTime
	 * @return type
	 */
	protected function sortSubscribers($subscribers, $endTime) {
		$sorted = array();
		
		foreach ($subscribers as $subscriber) {
			if(!isset($subscriber['sid'])) {
				Billrun_Factory::log("Invalid subscriber record in filter subscribers!", Zend_Log::NOTICE);
				continue;
			}
			
			$sid = $subscriber['sid'];
			$sorted[$sid][] = $this->handleSubscriberDates($subscriber, $endTime);
		}
		
		return $sorted;
	}
	
	protected function handleSubscriberDates($subscriber, $endTime) {
		$to = $subscriber['to'];
		if(isset($subscriber['to']->sec)) {
			$to = $subscriber['to']->sec;
		}
		
		$from = $subscriber['from'];
		if(isset($subscriber['from']->sec)) {
			$from= $subscriber['from']->sec;
		}

		if($to > $endTime) {
			$to = $endTime;
		}
		
		$subscriber['firstname'] = $subscriber['first_name'];
		$subscriber['lastname'] = $subscriber['last_name'];
		$subscriber['sfrom'] = $from;
		$subscriber['sto'] = $to;
		$subscriber['from'] = date(Billrun_Base::base_datetimeformat, $from);
		$subscriber['to'] = date(Billrun_Base::base_datetimeformat, $to);
		
		return $subscriber;
	}
	
	/**
	 * Construct subscriber data
	 * Consructs the plans and services to be aggregated with the subscriber data
	 * @param type $subscribers
	 * @param Billrun_DataTypes_CycleTime $cycle
	 * @return array
	 */
	protected function constructSubscriberData($subscribers, $cycle) {
		$filtered = array();
		
		foreach ($subscribers as $sid => $current) {
			$filtered[$sid] = $this->buildSubAggregator($current, $cycle->end());
		}
		
		return $filtered;
	}
	
	/**
	 * Create a subscriber aggregator from an array of subscriber records.
	 * @param array $current - Array of subscriber records.
	 * @param int $endTime
	 * @todo: Rewrite this function better
	 */
	protected function buildSubAggregator(array $current, $endTime) {		
		$aggregatorData = array();
		
		$plan = null;
		$services = array();
		$servicesData = array();
		foreach ($current as $subscriber) {
			// Get the plan
			$currPlan = $subscriber['plan'];
			$arrPlanDate = $subscriber['plans'][count($subscriber['plans']) - 1];
			// If it is the same plan
			if(($currPlan && !$plan) || ($plan['name'] !== $currPlan)) {
				// Update the last plan
				if($plan) {
					$planEnd = $arrPlanDate['from'];
					Billrun_Factory::log("Plan end: " . $planEnd);
					$planStart = $plan['start'];
					if(!($planStart instanceof MongoDate)) {
						throw new Exception("For not plan dates are mongo dates");
					}
					
					$aggregatorData[$planEnd]['plans'][] = array("plan" => $plan['name'], "start" => $planStart->sec, "end" => $planEnd);
				}

				// Set the plan
				$plan['name'] = $currPlan;
				$plan['start'] = $arrPlanDate['plan_activation'];
			}
			
			// Get the services.
			$currServices = array();
			if(isset($subscriber['services'])) {
				$currServices = $subscriber['services'];
			}
			
			// Check the differences in the services.
			$removed  = array_diff($services, $currServices);
			$added = array_diff($currServices, $services);
			
			$services = $currServices;
			
			// Add the services to the services data
			foreach ($added as $addedService) {
				$key = $addedService['name'];
				$serviceStart = new MongoDate($subscriber['sfrom']);
				if(!($serviceStart instanceof MongoDate)) {
					Billrun_Factory::log("from " . $serviceStart);
					throw new Exception("For not plan dates are mongo dates");
				}
				$serviceRow = array("service" => $addedService, "start" => $serviceStart->sec);
				$servicesData[$key] = $serviceRow;
			}
			
			// Handle the removed services. 
			foreach ($removed as $removedService) {
				$key = $removedService['name'];
				$updateService = $servicesData[$key];
				unset($servicesData[$key]);
				$serviceEnd = $arrPlanDate['from'];
				$updateService['end'] = $serviceEnd;
				
				// Push back
				$aggregatorData[$serviceEnd]['services'][] = $updateService;
			}
		}
		
		// Handle all the remaining values.
		if($plan) {
			$planStart = $plan['start'];
			if(!($planStart instanceof MongoDate)) {
				throw new Exception("For not plan dates are mongo dates");
			}
			$planData = array("plan" => $plan['name'], "start" => $planStart->sec, "end" => $endTime);
			$aggregatorData[$endTime]['plans'][] = $planData;
			$aggregatorData[$endTime]['next_plan'] = $plan;
		}
		
		foreach ($servicesData as $lastService) {
			$lastService['end'] = $endTime;
			$aggregatorData[$endTime]['services'][] = $lastService;
		}
		
		return $aggregatorData;
	}
}
