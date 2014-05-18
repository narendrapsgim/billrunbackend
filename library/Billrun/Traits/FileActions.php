<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * This Trait is used to allow classes to  parse file names an  extract  data  from them. 
 *
 * @author eran
 */
trait Billrun_Traits_FileActions {
	

	/**
	 * the backup sequence file number digits granularity 
	 * (1=batches of 10 files  in each directory, 2= batches of 100, 3= batches of 1000,etc...)
	 * @param integer
	 */
	protected $backup_seq_granularity = 2;// 100 files in each directory.
	
	/**
	 * An array of path to back files to.
	 * @var Array containg the paths  to save backups to.
	 */
	protected $backupPaths = array();
	
	
	/**
	 *
	 * @var boolean whether to preserve the modification timestamps of the received files
	 */
	protected $preserve_timestamps = true;

	
	/**
	 * Get the data the is stored in the file name.
	 * @return an array containing the sequence data. ie:
	 * 			array(seq => 00001, date => 20130101 )
	 */
	public function getFilenameData($filename,$configPrefix) {
		return array(
			'seq' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($configPrefix . ".sequence_regex.seq", "/\D(\d+)\D/"), $filename),
			'date' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($configPrefix . ".sequence_regex.date", "/\D(20\d{4})\D/"), $filename),
			'time' => Billrun_Util::regexFirstValue(Billrun_Factory::config()->getConfigValue($configPrefix . ".sequence_regex.time", "/20\d{4}.*\D(\d{4,6})\D/"), $filename),
		);
	}
	

	/**
	 * Verify that the file is a valid file. 
	 * @return boolean false if the file name should not be received true if it should.
	 */
	protected function isFileValid($filename, $path) {
		//igonore hidden files
		return preg_match(( $this->filenameRegex ? $this->filenameRegex : "/^[^\.]/"), $filename);
	}
	
	
	/**
	 * method to check if the file already processed
	 */
	protected function isFileReceived($filename, $type, $more_fields = array()) {
		$log = Billrun_Factory::db()->logCollection();

		$query = array(
			'source' => $type,
			'file_name' => $filename,
		);

		if (!empty($more_fields)) {			
			$query = array_merge($query, Billrun_Util::arrayToMongoQuery($query));
		}

		Billrun_Factory::dispatcher()->trigger('alertisFileReceivedQuery', array(&$query, $type, $this));
		$resource = $log->query($query)->cursor()->limit(1);
		return $resource->count() > 0;
	}
	
	/**
	 * Backup the current processed file to the proper backup paths
	 * @param type $move should the file be moved when the backup ends?
	 */
	protected function backup($filePath, $filename, $backupPaths, $retrievedHostname, $move = true) {
		$backupPaths = is_array($backupPaths) ? $backupPaths : array($backupPaths);
		
		$seqData = $this->getFilenameData($filename,$this->getType());
		$backedTo = array();
		for ($i = 0; $i < count($backupPaths); $i++) {
			$backupPath = $this->generateBackupPath($backupPaths[$i],$seqData,$retrievedHostname);
			$this->prepareBackupPath($backupPath);
			if ($this->backupToPath($filePath,$backupPath, $this->preserve_timestamps,!($move && $i + 1 == count($backupPaths))) === TRUE) {
				Billrun_Factory::log()->log("Success backup file " . $filePath . " to " . $backupPath, Zend_Log::INFO);
				$backedTo[] = $backupPath;
			} else {
				Billrun_Factory::log()->log("Failed backup file " . $filePath . " to " . $backupPath, Zend_Log::INFO);
			}
		}
		
		return $backedTo;
	}
	/**
	 * Generate a backup path to a given file.
	 * @param type $basePath the base of the backup path.
	 * @param type $fileSeqData the file sequence data.
	 * @param type $retrievedHostname the host the file was retrived from.
	 * @return string the path to place the file in.
	 */
	public function generateBackupPath($basePath,$fileSeqData,$retrievedHostname = false) {
			$backupPath = $basePath;
			$backupPath .= ($retrievedHostname ? DIRECTORY_SEPARATOR . $retrievedHostname : ""); //If theres more then one host or the files were retrived from a named host backup under that host name
			$backupPath .= DIRECTORY_SEPARATOR . ($fileSeqData['date'] ? $fileSeqData['date'] : date("Ym")); // if the file name has a date  save under that date else save under tthe current month
			$backupPath .= ($fileSeqData['seq'] ? DIRECTORY_SEPARATOR . substr($fileSeqData['seq'], 0, - $this->backup_seq_granularity) : ""); // brak the date to sequence number with varing granularity
			
			return $backupPath;
	}

	/**
	 * method to backup the processed file
	 * @param string $trgtPath  the path to backup the file to.
	 * @param boolean $copy copy or rename (move) the file to backup
	 * 
	 * @return boolean return true if success to backup
	 */
	public function backupToPath($srcPath, $trgtPath, $preserve_timestamps = true, $copy = true) {
		if ($copy) {
			$callback = "copy";
		} else {
			$callback = "rename"; // php move
		}
		
		if (!file_exists($trgtPath)) {
			@mkdir($trgtPath, 0777, true);
		}
		
		
		$filename = basename($srcPath);
		$target_path = $trgtPath . DIRECTORY_SEPARATOR . $filename;
		Billrun_Factory::log()->log("Backing up file from : " . $srcPath . " to :  " . $trgtPath, Zend_Log::INFO);
		$timestamp = filemtime($srcPath); // this will be used after copy/move to preserve timestamp
		$ret = @call_user_func_array($callback, array(
				$srcPath,
				$target_path,
		));		
		if ($preserve_timestamps) {			
			Billrun_Util::setFileModificationTime($target_path, $timestamp);
		}
		
		return $ret;
	}
	/**
	 * Make sure the file can be coppied/moved to a given backup path.
	 * @param type $paththe path to prepare for backup.
	 * @return boolean
	 */
	protected function prepareBackupPath($path) {
		if (!file_exists($path) && !@mkdir($path, 0777, true)) {
				Billrun_Factory::log()->log("Can't create backup path or is not a directory " . $path, Zend_Log::WARN);
				return FALSE;
			}
			
			// in case the path exists but it's a file
			if (!is_dir($path)) {
				Billrun_Factory::log()->log("The path " . $path . " is not directory", Zend_Log::WARN);
				return FALSE;
			}
		return $path;	
	}
	
	/**
	 * Set the backup granularity.
	 * @param type $grn the digit  granularity count.
	 */
	protected function setGranularity($grn) {
		$this->backup_seq_granularity = intval($grn);
	}
	
	/**
	 * Remove the file if its backed up if not back it  in a default folder.
	 * @param type $filepath the stamp to the file to remove from workspace
	 */
	protected function removeFromWorkspace($filestamp) {
		$file = Billrun_Factory::db()->logCollection()->query(array('stamp'=> $filestamp))->cursor()->limit(1)->current();
		if(!$file->isEmpty()) {
			$defaultBackup = Billrun_Factory::config()->getConfigValue('backup.default_backup_path',FALSE);			
			if(empty($file['extra_data']['backed_to'])) {
				$backupPaths =  !empty($this->backupPaths) ? $defaultBackup : (!empty($defaultBackup) ? $defaultBackup : array('./backup/' . $this->getType()));
				Billrun_Factory::log()->log("Backing up and moving file {$file['path']} to - ".implode(",", $backupPaths) , Zend_Log::INFO);	
				$this->backup(basename($file['path']),$file['path'], $backupPaths, $file['retrived_from'],true);
			} else {
				Billrun_Factory::log()->log("File {$file['path']}  already backed up to :".implode(",",$file['extra_data']['backed_to']), Zend_Log::INFO);
				Billrun_Factory::log()->log("Removing file {$file['path']} from the workspace", Zend_Log::INFO);
				unlink($file['path']);
			}
		}
	}
	
	/**
	 * retrive the type name of the using class instance.
	 * @return string the  class instance name
	 */
	abstract function  getType();
	
	
	
}
