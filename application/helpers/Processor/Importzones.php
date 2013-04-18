<?php

/**
 * @category   Application
 * @package    Helpers
 * @subpackage Processor
 * @copyright  Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

/**
 * Billing processor for import data into rates collection
 *
 * @package    Processor
 * @subpackage ImportZones
 * @since      1.0
 */
class Processor_ImportZones extends Billrun_Processor_Base_Separator {

	/**
	 * the type of the object
	 *
	 * @var string
	 */
	static protected $type = 'importzones';

	public function __construct($options) {
		parent::__construct($options);
		$header = $this->getLine();
		$this->parser->setStructure($header);
	}
	
	/**
	 * we do not need to log
	 * 
	 * @return boolean true
	 */
	public function logDB() {
		return TRUE;
	}
	/**
	 * method to parse the data
	 */
	protected function parse() {
		if (!is_resource($this->fileHandler)) {
			Billrun_Factory::log()->log("Resource is not configured well", Zend_Log::ERR);
			return false;
		}


		while ($line = $this->getLine()) {
			$this->parseData($line);
		}

		return true;
	}

	/**
	 * method to parse data
	 * 
	 * @param array $line data line
	 * 
	 * @return array the data array
	 */
	protected function parseData($line) {
		$this->parser->setLine($line);
		Billrun_Factory::dispatcher()->trigger('beforeDataParsing', array(&$line, $this));
		$row = $this->parser->parse();
		$row['source'] = static::$type;
		$row['type'] = self::$type;
//		$row['header_stamp'] = $this->data['header']['stamp'];
		$row['file'] = basename($this->filePath);
		$row['process_time'] = date(self::base_dateformat);
		Billrun_Factory::dispatcher()->trigger('afterDataParsing', array(&$row, $this));
		$this->data['data'][] = $row;
		return $row;
	}
	
	protected function store() {
		if (!isset($this->data['data'])) {
			// raise error
			return false;
		}

		$rates = Billrun_Factory::db()->ratesCollection();

		$data = $this->normalize($this->data['data']);
		$this->data['stored_data'] = array();

		foreach ($data as $key => $row) {
			$entity = new Mongodloid_Entity($row);
			
			if ($rates->query('key', $key)->count() > 0) {
				continue;
			}

			$entity->save($rates, true);
			$this->data['stored_data'][] = $row;
		}

		return true;
	}
	
	protected function normalize($data) {
		$ret = array();
		foreach ($data as $row) {
			if (!isset($row['zoneName'])) {
				print_R($row);
				continue;
			}
			$key = $row['zoneName'];
			if (!isset($ret[$key])) {
				$ret[$key] = array(
					'from' => new MongoDate(strtotime('2013-01-01T00:00:00+00:00')),
					'key' => $row['zoneName'],
					'destinations' => array($row['prefix']),
				);
			} else {
				$ret[$key]['destinations'][] = $row['prefix'];
			}
		}

		return $ret;
		
	}


}
