<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */
require_once APPLICATION_PATH . '/application/controllers/Action/Api.php';

/**
 * Recreate invoices action class
 *
 * @package  Action
 * @since    0.5
 */
class SaveversionAction extends ApiAction {
	public function execute() {
		Billrun_Factory::log("Execute save version", Zend_Log::INFO);
		$request = $this->getRequest()->getRequest(); // supports GET / POST requests
		$collection = $request['collection'];
		$name = $request['name'];
		Billrun_Factory::log("Exporting " . $collection, Zend_Log::INFO);
		
		$path = VersionsModel::getVersionsPath($collection) . '/' . $name;
		$collectionName = $collection . 'Collection';
		if (!$coll = Billrun_Factory::db()->{$collectionName}()) {
			Billrun_Factory::log('Saveversion - cannot read collection ' . $collectionName, Zend_Log::ERR);
			return false;
		}
		$entries = $coll->query()->cursor();
		if (!$file = fopen($path, "w")) {
			Billrun_Factory::log('Saveversion - cannot open file ' . $file . ' for writing', Zend_Log::ERR);
			return false;
		}
		$output = array();
		foreach ($entries as $entry) {
			$output[] = json_encode($entry->getRawData());
		}
		if (!fwrite($file, implode(VersionsModel::getDelimiter(), $output))) {
			Billrun_Factory::log('Saveversion - cannot write to file ' . $file . '. Data: ' . print_R($output, 1), Zend_Log::ERR);
			fclose($file);
			return false;
		}
		fclose($file);
		return true;
	}

}
