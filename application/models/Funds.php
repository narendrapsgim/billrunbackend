<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Funds model class
 *
 * @package  Models
 * @subpackage Table
 * @since    0.5
 */
class FundsModel extends TableModel {

	public function __construct(array $params = array()) {
		$params['collection'] = Billrun_Factory::db()->funds;
		parent::__construct($params);
		//$this->search_key = "";
	}

	public function storeData($data) {
		$entity = new Mongodloid_Entity($data);
		return $entity->save($this->collection, 1);
	}

}
