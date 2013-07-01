<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Billing generic util class
 *
 * @package  Util
 * @since    0.5
 */
class Billrun_Util {

	/**
	 * method to receive full datetime of last billrun time
	 * 
	 * @param boolean $return_timestamp set this on if you need timestamp instead of string
	 * @param int $dayofmonth the day of the month require to get; if omitted return config value
	 * @return mixed date string of format YYYYmmddHHmmss or int timestamp 
	 */
	public static function getLastChargeTime($return_timestamp = false, $dayofmonth = null) {
		if (!$dayofmonth) {
			$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 25);
		}
		$format = "Ym" . $dayofmonth . "000000";
		if (date("d") >= $dayofmonth) {
			$time = date($format);
		} else {
			$time = date($format, strtotime('-1 month'));
		}
		if ($return_timestamp) {
			return strtotime($time);
		}
		return $time;
	}

	public static function joinSubArraysOnKey($arrays, $depth = 1, $key = false) {

		if ($depth == 0 || !is_array($arrays)) {
			return $arrays;
		}
		//	print_r($arrays);
		$retArr = array();
		foreach ($arrays as $subKey => $subArray) {
			if ($key) {
				$retArr[$subKey] = array($key => Billrun_Util::joinSubArraysOnKey($subArray, $depth - 1, $subKey));
			} else {
				$swappedArr = Billrun_Util::joinSubArraysOnKey($subArray, $depth - 1, $subKey);
				if (is_array($swappedArr)) {
					$retArr = array_merge_recursive($retArr, $swappedArr);
				} else {
					$retArr[$subKey] = $swappedArr;
				}
			}
		}
		return $retArr;
	}

	/**
	 * generate array stamp
	 * @param array $ar array to generate the stamp from
	 * @return string the array stamp
	 */
	public static function generateArrayStamp($ar) {
		return md5(serialize($ar));
	}

	/**
	 * generate current time from the base time date format
	 * 
	 * @return string the current date time formatted by the system default format
	 */
	public static function generateCurrentTime() {
		return date(Billrun_Base::base_dateformat);
	}

	/**
	 * Get the first value that match to a regex
	 * @param $pattern the regex pattern
	 * @param $subject the string to run the regex on.
	 * @param $resIndex (optional) the index to get , of the returned regex results.
	 * @return the first regex group  that match ed or false if there was no match
	 */
	public static function regexFirstValue($pattern, $subject, $resIndex = 1) {
		$matches = array();
		if (!preg_match(($pattern ? $pattern : "//"), $subject, $matches)) {
			return FALSE;
		}
		return (isset($matches[$resIndex])) ? $matches[$resIndex] : FALSE;
	}

	/**
	 * method to convert short datetime (20090213145327) into ISO-8601 format (2009-02-13T14:53:27+01:00)
	 * the method can be relative to timezone offset
	 * 
	 * @param string $datetime the datetime. can be all input types of strtotime function
	 * @param type $offset the timezone offset +/-xxxx or +/-xx:xx
	 * 
	 * @return MongoDate MongoObject
	 */
	public static function dateTimeConvertShortToIso($datetime, $offset = '+00:00') {
		if (strpos($offset, ':') === FALSE) {
			$tz_offset = substr($offset, 0, 3) . ':' . substr($offset, -2);
		} else {
			$tz_offset = $offset;
		}
		$date_formatted = str_replace(' ', 'T', date(Billrun_Base::base_dateformat, strtotime($datetime))) . $tz_offset;
		$datetime = strtotime($date_formatted);
		return $datetime;
	}

	public static function startsWith($haystack, $needle) {
		return !strncmp($haystack, $needle, strlen($needle));
	}

	/**
	 * method to receive billrun key by date
	 * 
	 * @param string $datetime the anchor datetime. can be all input types of strtotime function
	 * @param int $dayofmonth the day of the month require to get; if omitted return config value
	 * @return string date string of format YYYYmm
	 */
	public static function getBillrunKey($datetime, $dayofmonth = null) {
		if (!$dayofmonth) {
			$dayofmonth = Billrun_Factory::config()->getConfigValue('billrun.charging_day', 25);
		}
		$month = date("m", $datetime);
		$format = "Y" . $month;
		if (date("d", $datetime) < $dayofmonth) {
			$key = date($format);
		} else {
			$key = date($format, strtotime('+1 month'));
		}
		return $key;
	}

	public static function getFollowingBillrunKey($billrun_key) {
		$datetime = $billrun_key . "01000000";
		$month_later = strtotime('+1 month', strtotime($datetime));
		$ret = date("Ym", $month_later);
		return $ret;
	}

	/**
	 * convert corrency.  
	 * (this  should  be change to somthing more dynamic)
	 * @param type $value the value to convert.
	 * @param type $from the currency to conver from.
	 * @param type $to the currency to convert to.
	 * @return float the converted value.
	 */
	public static function convertCurrency($value, $from, $to) {
		$conversion = array(
			'ILS' => 1,
			'EUR' => 4.78,
			'USD' => 3.68,
		);

		return $value * ($conversion[$from] / $conversion[$to]);
	}

//	public static function deep_in_array($needle, $haystack) {
//		foreach ($haystack as $item) {
//			if (!is_array($item)) {
//				if ($item == $needle) {
//					return true;
//				} else {
//					continue;
//				}
//			}
//			if (in_array($needle, $item)) {
//				return true;
//			}
//			else if (self::deep_in_array($needle, $item)) {
//				return true;
//			}
//		}
//		return false;
//	}

}