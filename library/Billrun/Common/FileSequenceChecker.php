<?php


/**
 * This is an Helper class to allow sequence checking for plugins 
 * usage :
 *  provide $getFileSequenceDataCallable callable function that will return an array (array('seq'=> <number> , date => <date>))
 *  to the contructor.
 *  then call verifyFileSequence with the file name for each file you want to check.
 *
 * @author eran
 */
class Billrun_Common_FileSequenceChecker {

	public $lastLogFile = false;
	
	protected $getFileSequenceDataCallable = false;
	protected $hostname = '';
	protected $type = false;
	protected $sequenceArr = array();
	
	public function __construct( $getFileSequenceDataFunc, $host, $type) {
		$this->getFileSequenceDataCallable = $getFileSequenceDataFunc;
		$this->hostname = $host;
		$this->type = $type;
		$this->loadLastFileDataFromHost();
	}
	
	/**
	 * Check that the received files are in the proper order.
	 * @param $filename the recieve filename.
	 */
	public function addFileToSequence($filename) {
		$msg = FALSE;
		if(!$this->getFileSequenceDataCallable) {
			throw new Exception('getFileSequenceData Function wasn`t set on construction!');
		}
		if(!($sequenceData = call_user_func($this->getFileSequenceDataCallable, $filename))) {
			$msg = "GGSN Reciever : Couldnt parse received file : $filename !!!!";
			Billrun_Factory::log()->log($msg,  Zend_Log::ALERT);			
			return $msg;
		}
		
		$this->sequenceArr[intval($sequenceData['seq'],10)] =  $filename;
	
		return $msg;
	}
	
	/**
	 * Check if the files that were added has a  missing or out if syn sequence.
	 * @return bool|array false if the sequence is ok or anarry containg the files that were checked.
	 */
	public function hasSequenceMissing() {
		$highSeq = false;
		$ret = false;
		$lowSeq = false;
		ksort($this->sequenceArr);

		foreach($this->sequenceArr as $seq => $filename ) {
			if($lowSeq === false || $lowSeq > $seq) {
					$lowSeq = $seq;
			} 
			if($highSeq === false || $highSeq < $seq) {
					$highSeq = $seq;
			}
		}
		
		if(count($this->sequenceArr)-1 != $highSeq - $lowSeq) {
			$ret = array_values($this->sequenceArr);
		}
		
		return $ret;
	}
	
	protected function loadLastFileDataFromHost() {
		$db = Billrun_Factory::db();
		$log = $db->getCollection($db::log_table);
		$lastLogFile = $log->query()->equals('source',$this->type)->exists('received_time')
									->equals('retrieved_from',$this->hostname)->
									cursor()->sort(array('received_time' => -1))->limit(1)->rewind()->current();
		if( isset($lastLogFile['file_name']) ) {
			$this->lastLogFile = $lastLogFile;
			$lastSequenceData = call_user_func($this->getFileSequenceDataCallable, $lastLogFile->get('file_name'));
			$this->sequenceArr[intval($lastSequenceData,10)] =  $lastLogFile['file_name'];
			//Billrun_Factory::log()->log(print_r($this->lastSequenceData,1),  Zend_Log::DEBUG);
		}
	}

}
