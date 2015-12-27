<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * This is a parser to be used by the auto renew action.
 *
 * @author Tom Feigin
 * @todo This class is very similar to balances query, 
 * a generic query class should be created for both to implement.
 */
class Billrun_ActionManagers_Subscribersautorenew_Query extends Billrun_ActionManagers_APIAction{
	
	/**
	 * Field to hold the data to be written in the DB.
	 * @var type Array
	 */
	protected $query = array();
	
	const DEFAULT_ERROR = "Success querying auto renew";
	
	/**
	 */
	public function __construct() {
		parent::__construct(array('error' => self::DEFAULT_ERROR));
		$this->collection = Billrun_Factory::db()->subscribers_auto_renew_servicesCollection();
	}
	
	/**
	 * Get a plan record according to the subscribers auto renew record.
	 * @param Mongodloid_Entity $record
	 * @return plan record.
	 */
	protected function getPlanRecord($record) {
		$planCollection = Billrun_Factory::db()->plansCollection();
		$planQuery = Billrun_Util::getDateBoundQuery();
		$planQuery["name"] = $record['charging_plan_name'];
		$planQuery["type"] = 'charging';
		return $planCollection->query($planQuery)->cursor()->current();
	}
	
	/**
	 * Populate the plan values.
	 * @param Mongodloid_Entity $record - Record to populate with plan values.
	 */
	protected function populatePlanValues(&$record) {
		if(!isset($record['charging_plan_name'])) {
			$this->reportError("No plan found for recurring record!", Zend_Log::ERR);
			return false;
		}
		
		$planRecord = $this->getPlanRecord($record);
		if($planRecord->isEmpty()) {
			$this->reportError("Invalid plan for subscribers auto renew!", Zend_Log::ERR);
			return false;
		}
		
		if(!isset($planRecord['include'])) {
			// TODO: Is this an error?
			return true;
		}
		
		$includeList = $planRecord['include'];
		
		// TODO: Is this filtered by priority?
		// TODO: Should this include the total_cost??
		foreach ($includeList as $includeRoot => $includeValues) {
			if(!isset($includeValues['pp_includes_name'])) {
				continue;
			}
			
			$toAdd = array();
			
			// Set the record values.
			$toAdd['unit_type'] = $includeRoot;			
			$toAdd['ammount'] = $includeValues['usagev'];
			$record['includes'][$includeValues['pp_includes_name']] = $toAdd;
		}
		
		return true;
	}
	
	/**
	 * Query the subscribers collection to receive data in a range.
	 */
	protected function queryRange() {
		try {
			$cursor = $this->collection->query($this->query)->cursor();
			$returnData = array();
			$date_fields = array('from', 'to', 'last_renew_date', 'creation_time');
			// Going through the lines
			foreach ($cursor as $line) {
				$rawItem = $line->getRawData();
				
				if(!$this->populatePlanValues($rawItem)) {
					// TODO: What error is reported?
					return false;
				}
				$returnData[] = Billrun_Util::convertRecordMongoDatetimeFields($rawItem, $date_fields);
			}
		} catch (\Exception $e) {
			$error = 'failed quering DB got error : ' . $e->getCode() . ' : ' . $e->getMessage();
			$this->reportError($error, Zend_Log::ALERT);
			return null;
		}	
		
		return $returnData;
	}
	
	/**
	 * Execute the action.
	 * @return data for output.
	 */
	public function execute() {
		$returnData = 
			$this->queryRange();

		$success=true;
		// Check if the return data is invalid.
		if(!$returnData) {
			// If no internal error occured, report on empty data.
			if($this->error == self::DEFAULT_ERROR) {
				$this->reportError("No data returned for query", Zend_Log::ALERT);
			}
			$returnData = array();
			$success=false;
		}
		
		$outputResult = 
			array('status'  => ($success) ? (1) : (0),
				  'desc'    => $this->error,
				  'details' => $returnData);
		return $outputResult;
	}
	
	/**
	 * Parse the to and from parameters if exists. If not execute handling logic.
	 */
	protected function parseDateParameters() {
		if (!isset($this->query['from'])){
			$this->query['from']['$lte'] = new MongoDate();
		} else {
			$this->query['from'] = new MongoDate(strtotime($this->query['from']));
		}
		if (!$this->query['to']) {
			$this->query['to']['$gte'] = new MongoDate();
		} else {
			$this->query['to'] = new MongoDate(strtotime($this->query['to']));
		}
	}
	
	/**
	 * Parse the received request.
	 * @param type $input - Input received.
	 * @return true if valid.
	 */
	public function parse($input) {
		if(!$this->setQueryRecord($input)) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Set the values for the query record to be set.
	 * @param httpRequest $input - The input received from the user.
	 * @return true if successful false otherwise.
	 */
	protected function setQueryRecord($input) {
		$jsonData = null;
		$query = $input->get('query');
		if(empty($query) || (!($jsonData = json_decode($query, true)))) {
			$error = "Failed decoding JSON data";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		if(!isset($jsonData['sid'])) {
			$error = "Did not receive an SID argument";
			$this->reportError($error, Zend_Log::ALERT);
			return false;
		}
		
		$this->query = $jsonData;
		$this->parseDateParameters();
		
		return true;
	}
}
