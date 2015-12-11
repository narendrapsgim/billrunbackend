<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2015 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing Importer Csv Simple
 * Imports from Csv to mongo collection
 *
 * @package  Billrun
 * @since    5.0
 */
class Billrun_Importer_Csv_Simple extends Billrun_Importer_Csv {
	
	protected $collectionName = null;
	protected $fieldToImport = null;
	protected $dataToImport = null;
	protected $importerName = null;
	
	public function __construct($options) {
		parent::__construct($options);
		Billrun_Factory::config()->addConfig(APPLICATION_PATH . "/conf/importers/conf.ini");
	}
	
	protected function getCollectionName() {
		$ret = pathinfo($this->getPath(), PATHINFO_FILENAME);
		return $ret;
	}
	
	/**
	 * Gets the fields to save in the document
	 * 
	 * @return type
	 */
	protected function getImporterFields() {
		if (empty($this->fieldToImport)) {
			$this->fieldToImport = fgetcsv($this->handle, $this->limit, $this->delimiter);
		}
		return $this->fieldToImport;
	}
	
	public function getRowsIndexesToSkip() {
		return array();
	}

	protected function getDataToSave($rowData) {
		$ret = array();
		foreach ($this->fields as $field => $rowFieldIndex) {
			$ret[$rowFieldIndex] = $rowData[$field];
		}
		return $ret;
	}

	
}