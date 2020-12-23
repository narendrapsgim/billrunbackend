<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2020 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing suggestions rate recalculation class
 *
 * @package  Billing
 */
class Billrun_Suggestions_RateRecalculation extends Billrun_Suggestions {

	public function __construct() {
		parent::__construct();
	}

	protected function getRecalculateType() {
		return 'rates';
	}

	protected function getCollectionName() {
		return 'rates';
	}

	protected function checkIfValidRetroactiveChange($retroactiveChange) {
		//check if price change
		if (!$this->isFirstTierPriceChange($retroactiveChange)) {
			return false;
		}
		$newRate = $retroactiveChange['new'];

		//Doesn't contain more than one tier and if that tier's interval is 1
		//OR
		//Contains one tier with from=0, to=interval and others have a 0 price.
		if ($this->checkTierAndInterval($newRate, 1, 1) ||
			$this->checkTierAndIntervalEqualAndOthersTiersPriceZero($newRate, 1)) {
			return true;
		}
		return false;
	}

	protected function getFieldNameOfLine() {
		return 'arate_key';
	}

	protected function recalculationPrice($line) {
		$updateRate = Billrun_Rates_Util::getRateByName($line['key'], $line['from']->sec);
		$usageType = Billrun_Rates_Util::getRateUsageType($updateRate);
		$newPrice = Billrun_Rates_Util::getTotalCharge($updateRate, $usageType, $line['usagev']);
		//Doesn't contain more than one tier and if that tier's interval is 1
		if ($this->checkTierAndInterval($updateRate, 1, 1)) {
			return $newPrice;
		} else {//Contains one tier with from=0, to=interval and others have a 0 price.
			return $newPrice * $line['total_lines'];
		}
	}

	protected function addGroupsIdsForMatchingLines() {
		return array(
			'plan' => '$plan',
			'services' => '$services'
		);
	}

	protected function addProjectsForMatchingLines() {
		return array('plan' => '$_id.plan', 'services' => '$_id.services');
	}

	protected function checkIfValidLine($line) {
		$rate_key = $line['key'];
		
		//check if rate include/overrride in plan -> return false
		$planData = Billrun_Plan::getByNameAndTime($line['plan'], $line['from']);
		if (Billrun_Rates_Util::checkIfRateInclude($rate_key, $planData) ||
			Billrun_Rates_Util::checkIfRateOverride($rate_key, $planData)) {
			return false;
		}

		//check if rate include/overrride in services -> return false
		foreach ($line['services'] as $service) {
			$serviceData = Billrun_Service::getByNameAndTime($service, $line['from']);
			if (Billrun_Rates_Util::checkIfRateInclude($rate_key, $serviceData) ||
				Billrun_Rates_Util::checkIfRateOverride($rate_key, $serviceData)) {
				return false;
			}
		}
		return true;
	}

	private function isFirstTierPriceChange($retroactiveChange) {
		$tier = 1;
		$oldRate = $retroactiveChange['old'];
		$newRate = $retroactiveChange['new'];
		$oldPrice = Billrun_Rates_Util::getRateTierPrice($oldRate, $tier);
		$newPrice = Billrun_Rates_Util::getRateTierPrice($newRate, $tier);
		return $oldPrice !== $newPrice;
	}

	private function checkAllTiersPriceAreZero($rate, $excludeTiers) {
		foreach (Billrun_Rates_Util::getRateNumberOfTiers($rate) as $tier) {
			if (in_array($tier, $excludeTiers)) {
				continue;
			}
			if (Billrun_Rates_Util::getRateTierPrice($rate, $tier) != 0) {
				return false;
			}
		}
		return true;
	}

	private function checkTierAndInterval($rate, $tier = 1, $interval = 1) {
		if (Billrun_Rates_Util::getRateNumberOfTiers($rate) == $tier &&
			Billrun_Rates_Util::getRateTierInterval($rate, $tier) == $interval) {
			return true;
		}
		return false;
	}

	private function checkTierAndIntervalEqualAndOthersTiersPriceZero($rate, $tier = 1) {
		$excludeTiers = [$tier];
		if (Billrun_Rates_Util::getRateTierInterval($rate, $tier) == Billrun_Rates_Util::getRateTierTo($rate, $tier) && $this->checkAllTiersPriceAreZero($rate, $excludeTiers)) {
			return true;
		}
		return false;
	}

}
