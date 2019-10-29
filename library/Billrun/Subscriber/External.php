<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Billrun_Subscriber_External extends Billrun_Subscriber {
	
	static $queriesLoaded = false;
	
	static protected $type = 'external';
	
	protected static $queryBaseKeys = ['id', 'time', 'limit'];
		
	public function __construct($options = array()) {
		parent::__construct($options);
		$this->remote = Billrun_Factory::config()->getConfigValue('subscriber.fields.external', '');
	}
	
	public function delete() {
		return true;
	}

	public function getCredits($billrun_key, $retEntity = false) {
		return array();
	}

	public function getList($startTime, $endTime, $page, $size, $aid = null) {
		
	}

	protected function getSubscribersDetails($params, $availableFields = []) {
		$res = Billrun_Util::sendRequest($this->remote, json_encode($params));
		$subscribers = [];
		if (!$res) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		foreach ($res as $sub) {
			$subscribers[] = new Mongodloid_Entity($sub);
		}
		return $subscribers;
	}
	
	protected function getSubscriberDetails($queries) {
		$externalQuery = [];
		foreach ($queries as &$query) {
			$query = $this->buildParams($query);
			$externalQuery[] = $query;
		}
		$results = Billrun_Util::sendRequest($this->remote, json_encode($externalQuery));
		if (!$results) {
			Billrun_Factory::log()->log(get_class() . ': could not complete request to' . $this->remote, Zend_Log::NOTICE);
			return false;
		}
		return array_reduce($results, function($acc, $currentSub) {
			$acc[] = new Mongodloid_Entity($currentSub);
			return $acc;
		}, []);
	}

	public function isValid() {
		return true;
	}

	public function save() {
		return true;
	}
	
	protected function buildParams(&$query) {

		if (isset($query['EXTRAS'])) {
			unset($query['EXTRAS']);
		}
		$params = [];
		foreach ($query as $key => $value) {
			if (!in_array($key, static::$queryBaseKeys)) {
				$params[] = [
					'key' => $key,
					'operator' => 'equal',
					'value' => $value
					];
				unset($query[$key]);
			}
		}
		$query['params'] = $params;
		return $query;
	}
	
}

