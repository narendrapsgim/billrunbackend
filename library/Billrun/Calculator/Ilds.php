<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing calculator class for ilds records
 *
 * @package  calculator
 * @since    1.0
 */
class Billrun_Calculator_Ilds extends Billrun_Calculator {

	/**
	 * the type of the calculator
	 * @var string
	 */
	protected $type = 'ilds';

	/**
	 * execute the calculation process
	 */
	public function calc() {

		$this->dispatcher->trigger('beforeCalcData', array('data' => $this->data));
		foreach ($this->data as $item) {
			$this->updateRow($item);
		}
		$this->dispatcher->trigger('afterCalcData', array('data' => $this->data));
	}

	/**
	 * execute write down the calculation output
	 */
	public function write() {
		$this->dispatcher->trigger('beforeCalcWriteData', array('data' => $this->data));
		$lines = $this->db->getCollection(self::lines_table);
		foreach ($this->data as $item) {
			$item->save($lines);
		}
		$this->dispatcher->trigger('afterCalcWriteData', array('data' => $this->data));
	}

	/**
	 * write the calculation into DB
	 */
	protected function updateRow($row) {
		$this->dispatcher->trigger('beforeCalcWriteRow', array('row' => $row));
		
		$current = $row->getRawData();
		$charge = $this->calcChargeLine($row->get('type'), $row->get('call_charge'));
		$added_values = array(
			'price_customer' => $charge,
			'price_provider' => $charge,
		);
		$newData = array_merge($current, $added_values);
		$row->setRawData($newData);
		
		$this->dispatcher->trigger('afterCalcWriteRow', array('row' => $row));
	}

	/**
	 * method to calculate the charge from flat rate
	 * 
	 * @param string $type the type of the charge (depend on provider)
	 * @param double $charge the amount of charge
	 * @return double the amount to charge
	 * 
	 * @todo: refactoring it by mediator or plugin system
	 */
	protected function calcChargeLine($type, $charge) {
		switch ($type):
			case '012':
			case '014':
			case '015':
				$rating_charge = round($charge / 1000, 3);
				break;

			case '013':
			case '018':
				$rating_charge = round($charge / 100, 2);
				break;
			default:
				$rating_charge = $charge;
		endswitch;
		return $rating_charge;
	}

}