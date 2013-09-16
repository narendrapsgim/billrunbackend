<?php

/**
 * Temporary plugin  to handle smsc/smpp/mmsc retrival should be changed to specific CDR  handling baviour
 *
 * @author eran
 */
class smsPlugin extends Billrun_Plugin_BillrunPluginBase {
	
	/**
	 * plugin name
	 *
	 * @var string
	 */
	protected $name = 'sms';
	/**
	 * An HACK Copy  smsc and smpp to a thrid party directory.
	 * @param type $receiver the receiver instance
	 * @param type $filepaths the received file paths.
	 * @param type $hostname the  "hostname" the file  were recevied from
	 */
	public function afterFTPReceived($receiver,  $filepaths , $hostname) {
		if($receiver->getType() != 'smsc' && $receiver->getType() != "smpp" && $receiver->getType() != "mmsc" ) { return; } 
		$path = Billrun_Factory::config()->getConfigValue($receiver->getType().'.thirdparty.backup_path',false,'string');
		
		if(!$path) return;
		if( $hostname ) {
			$path = $path . DIRECTORY_SEPARATOR . $hostname;
		}
		Billrun_Factory::log()->log("Making directory : $path" , Zend_Log::DEBUG);
		if(!file_exists($path)) {
			if(mkdir($path, 0777, true)) {
				Billrun_Factory::log()->log("Failed when trying to create directory : $path" , Zend_Log::ERR);
			}
		}
		Billrun_Factory::log()->log(" saving retrieved files to third party at : $path" , Zend_Log::DEBUG);
		foreach($filepaths as $srcPath) {
			if(!copy($srcPath, $path .DIRECTORY_SEPARATOR. basename($srcPath))) {
				Billrun_Factory::log()->log(" Failed when trying to save file : ".  basename($srcPath)." to third party path : $path" , Zend_Log::ERR);
			}
		}
	}
}

?>
