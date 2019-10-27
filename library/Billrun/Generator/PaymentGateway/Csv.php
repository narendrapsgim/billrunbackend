<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2019 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Generator CSV for payment gateways files
 */
class Billrun_Generator_PaymentGateway_Csv {
	
	protected $data = array();
	protected $headers = array();
	protected $trailers = array();
	protected $delimiter;
	protected $fixedWidth = false;
	protected $padDirDef = STR_PAD_LEFT;
	protected $padCharDef = ' ';
        protected $file_name;
	protected $file_path;
        protected $local_dir;
        protected $encoding = 'utf-8';

        public function __construct($options) {
		$this->fixedWidth = isset($options['type']) && ($options['type'] == 'fixed') ? true : false;
		$this->data = isset($options['data']) ? $options['data'] : $this->data;
		$this->headers = isset($options['headers']) ? $options['headers'] : $this->headers;
		$this->trailers = isset($options['trailers']) ? $options['trailers'] : $this->trailers;
                $this->encoding = isset($options['configByType']['generator']['encoding']) ? $options['configByType']['generator']['encoding'] : $this->encoding;
		if (isset($options['delimiter'])) {
			$this->delimiter = $options['delimiter'];
		} else if ($this->fixedWidth) {
			$this->delimiter = '';
		}
                if (isset($options['local_dir'])) {
                    $this->local_dir = $options['local_dir'];
                }
	}
        
	/**
	 * validate the config.
	 *
	 * @param  array   $options   Relevant params from the config
	 * @return true in case all the expected config params exist, false otherwise.
	 */
	public static function validateOptions($config) {
		if (isset($config['generator']['type']) && !in_array($config['generator']['type'], array('fixed', 'separator'))) {
			return "File type isn't fixed/separator";
		}
		if (!isset($config['export']['export_directory'])) {
			return "File's local_dir is undefined";
		}
		if ($config['generator']['type'] === 'fixed') {
                  if(count($config['generator']['data_structure']) > 0){
			foreach ($config['generator']['data_structure'] as $dataLine) {
                                if (!isset($dataLine['padding']['length'])) {
                                    return "Missing padding length definitions for " . $config['generator']['type'];
                                }
			}
                  }
		}
		return true;
	}
	
	public function generate() {
		if (count($this->data)) {
			$this->writeHeaders();
			$this->writeRows();
			$this->writeTrailers();
		}
		return;
	}
	
	protected function writeToFile($str) {
                $str = iconv('utf-8', $this->encoding . '//TRANSLIT', $str);
		return file_put_contents($this->file_path, $str, FILE_APPEND);
	}

	protected function writeHeaders() {
		$fileContents = '';
		$counter = 0;
		foreach ($this->headers as $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$fileContents .= $this->getRowContent($entity);
			$fileContents .= PHP_EOL;
			if ($counter == 50000) {
				$this->writeToFile($fileContents);
				$fileContents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($fileContents);
	}
	
	protected function writeTrailers() {
		$fileContents = '';
		$counter = 0;
		foreach ($this->trailers as $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$fileContents .= $this->getRowContent($entity);
			$fileContents .= PHP_EOL;
			if ($counter == 50000) {
				$this->writeToFile($fileContents);
				$fileContents = '';
				$counter = 0;
			}
		}
		$this->writeToFile($fileContents);
	}
		
	protected function writeRows() {
		$fileContents = '';
		$counter = 0;
		foreach ($this->data as $index => $entity) {
			$counter++;
			if (!is_array($entity)) {
				$entity = $entity->getRawData();
			}
			$fileContents .= $this->getRowContent($entity);
			if ($index < count($this->data) - 1){
				$fileContents.= PHP_EOL;
			}
			if ($counter == 50000) {
				$this->writeToFile($fileContents);
				$fileContents = '';
				$counter = 0;
			}
		}
		if (!empty($this->trailers)) {
			$fileContents.= PHP_EOL;
		}
		$this->writeToFile($fileContents);
	}
	
	protected function getRowContent($entity) {
		$rowContents = '';
		$flag = 0;
		foreach ($entity as $entityObj) {
			$padDir = isset($entityObj['padding']['direction']) ? $this->getPadDirection($entityObj['padding']['direction']) : $this->padDirDef;
			$padChar = isset($entityObj['padding']['character']) ? $entityObj['padding']['character'] : $this->padCharDef;
                        if($this->fixedWidth){
                            $length = isset($entityObj['padding']['length']) ? $entityObj['padding']['length'] : strlen($entityObj['value']);
                        }else{
                            if(isset($entityObj['padding']['length'])){
                                $length = $entityObj['padding']['length'];
                            }else{
                                $length = strlen((isset($entityObj['value']) ? $entityObj['value'] : ''));
                            }
                        }
                        if($this->fixedWidth){
                            $rowContents.=str_pad((isset($entityObj['value']) ? $entityObj['value'] : ''), $length, $padChar, $padDir);
                        }else{
                            if($flag == 0){
                                $rowContents.=str_pad((isset($entityObj['value']) ? $entityObj['value'] : ''), $length, $padChar, $padDir);
                                $flag = 1;
                            }else{
                                $rowContents.= $this->delimiter . str_pad((isset($entityObj['value']) ? $entityObj['value'] : ''), $length, $padChar, $padDir);
                            }
                        }
			
		}
		return $rowContents;
	}

	protected function getPadDirection($dirStr) {
		switch ($dirStr) {
			case 'left':
				return STR_PAD_LEFT;
			case 'right':
				return STR_PAD_RIGHT;
			default:
				return $this->padDirDef;
		}
	}
	
	protected function getDelimetedLine($rowEntityDef) {
		$rowValues = array_column($rowEntityDef, 'value');
		return implode($this->delimiter, $rowValues);
	}

        public function setFileName($fileName){
            $this->file_name = $fileName;
        }
        
        public function setFilePath($dir){
            $this->file_path = $dir . '/' . $this->file_name;
        }
}

