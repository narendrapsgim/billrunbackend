<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2016 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billing tarit to add foreign fields logic to generated cdr/line
 *
 * @package  Trait
 * @since    0.5
 */

trait Billrun_Traits_ForeignFields  {

	private $foreginFieldPrefix = 'foreign';

	/**
	 * This array  will hold all the  added foregin fields that  were added to the CDR/row/line.
	 */
	protected $addedForeignFields = array();
	
	
	protected function getAddedFoerignFields() {
		return array_keys($this->addedForeignFields);
	}
	
	protected function clearAddedForeignFields() {
		$this->addedForeignFields = array();
	}
	

	protected function getForeignFields($foreignEntities, $existsingFields = array(), $autoLoadEntities = FALSE, $fullData = array()) {
		$foreignFieldsData = !empty($existsingFields) ? $existsingFields : array();
		$foreignFieldsConf = array_filter(Billrun_Factory::config()->getConfigValue('lines.fields', array()), function($value) {
			return isset($value['foreign']);	
		});
		
		foreach ($foreignFieldsConf as $fieldConf) {
			if(!preg_match('/^'.$this->foreginFieldPrefix.'\./',$fieldConf['field_name'])) {
				Billrun_Factory::log("Foreign field configuration not mapped to foreign sub-field",Zend_Log::WARN);
				continue;
			}
			if( $autoLoadEntities && empty($foreignEntities[$fieldConf['foreign']['entity']]) && empty(Billrun_Util::getIn($foreignFieldsData,$fieldConf['field_name']))
				&& (!is_array($autoLoadEntities) || in_array($fieldConf['foreign']['entity'],$autoLoadEntities)) ) {
				$entityValue = Billrun_Utils_Usage::retriveEntityFromUsage(array_merge($foreignFieldsData,$fullData), $fieldConf['foreign']['entity'],$fieldConf);
				if($entityValue != null) {
					$foreignEntities[$fieldConf['foreign']['entity']] = $entityValue;
				}
			}
			if (!empty($foreignEntities[$fieldConf['foreign']['entity']]) ) {
				if(!is_array($foreignEntities[$fieldConf['foreign']['entity']]) || Billrun_Util::isAssoc($foreignEntities[$fieldConf['foreign']['entity']])) {
					$pathToInsert = $this->buildPathToInsert($fieldConf);
					if ($fieldConf['foreign']['result_type'] == 'array'){
						$value = array();
						foreach($foreignEntities[$fieldConf['foreign']['entity']] as $entitie){
							$value[] =  $this->getForeginEntityFieldValue($entitie, $fieldConf['foreign']);
						}
					}else{
						$value = $this->getForeginEntityFieldValue($foreignEntities[$fieldConf['foreign']['entity']], $fieldConf['foreign']);
					}
					Billrun_Util::setIn($foreignFieldsData, $pathToInsert, $value);
				} else {
					foreach ($foreignEntities[$fieldConf['foreign']['entity']] as $idx => $foreignEntity) {
						Billrun_Util::setIn($foreignFieldsData, $fieldConf['field_name'].'.'.$idx, $this->getForeginEntityFieldValue($foreignEntity, $fieldConf['foreign']));
					}
				}
				$this->addedForeignFields[preg_replace('/\..+$/','',$fieldConf['field_name'])] = true;
			}
		}
		return $foreignFieldsData;
	}
	
	protected function getForeginEntityFieldValue($foreignEntity, $foreignConf) {
		if(is_object($foreignEntity) && method_exists($foreignEntity, 'getData')) {
			$foreignEntity = $foreignEntity->getData();
		}
		return $this->foreignFieldValueTranslation( Billrun_Util::getIn($foreignEntity, $foreignConf['field']), $foreignConf);
	}

	protected function foreignFieldValueTranslation($value, $foreignConf) {
		if(empty($foreignConf['translate'])) {
			return $value;
		}

		$translated = $value;
		switch($foreignConf['translate']['type']) {
			case 'unixTimeToString' : $translated = date(Billrun_Util::getFieldVal($foreignConf['translate']['format'],  Billrun_Base::base_datetimeformat),$value);
				break;
			case 'unixTimeToMongoDate' : $translated = new MongoDate($value);
				break;
			default: Billrun_Factory::log("Couldn't find translation function : {$foreignConf['translate']['type']}",Zend_Log::WARN);
		}

		return $translated;
	}
	
	protected function buildPathToInsert($foreignConf) {
		$entity = $foreignConf['foreign']['entity'];
		switch ($entity) {
			case 'tax':
				$pathToInsert = $foreignConf['foreign']['field'];
				break;
			default:
				$pathToInsert = $foreignConf['field_name'];
				break;
		}
		return $pathToInsert;
	}
}
