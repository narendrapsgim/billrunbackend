<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 S.D.O.C. LTD. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billrun model class
 *
 * @package  Models
 * @subpackage Table
 * @since    2.8
 */
class BillrunModel extends TableModel {

	public function __construct(array $params = array()) {
		$params['collection'] = 'billrun';
		$params['db'] = 'billrun';
		parent::__construct($params);
		$this->search_key = array('billrun_key', 'aid');
	}

}
