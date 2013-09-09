<?php

/**
 * @package         Mongodloid
 * @copyright       Copyright (C) 2012-2013 S.D.O.C. LTD. All rights reserved.
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */
class Mongodloid_Connection {

	protected $_connected = false;
	protected $_connection = null;
	protected $_persistent = false;
	protected $_server = '';
	protected $_dbs = array();
	protected static $instances;

	/**
	 * Method to get database instance
	 * 
	 * @param string $db the datainstace name
	 * @param string $user user to authenticate
	 * @param string $pass password to authenticate
	 * 
	 * @return Billrun_Db instance
	 */
	public function getDB($db, $user = false , $pass = false, array $options = array()) {
		if (!isset($this->_dbs[$db]) || !$this->_dbs[$db]) {
			$this->forceConnect($options);
			$newDb = $this->_connection->selectDB($db);
			if($user) {
				$newDb->authenticate($user,$pass);
			}
			
			$this->_dbs[$db] = $this->createInstance($newDb);
		}

		return $this->_dbs[$db];
	}
	
	/**
	 * create instance of the connection db
	 * 
	 * @param MongoDB $newDb The PHP Driver MongoDb instance
	 * 
	 * @return Mongodloid_Db instance
	 */
	protected function createInstance($newDb) {
		return new Mongodloid_Db($newDb, $this);
	}

	/**
	 * 	@throws MongoConnectionException
	 */
	public function forceConnect(array $options = array()) {
		if ($this->_connected)
			return;

		// this can throw an Exception
		$this->_connection = new MongoClient($this->_server ? $this->_server : 'localhost:27017', array());

		$this->_connected = true;
	}

	public function isConnected() {
		return $this->_connected;
	}

	public function isPersistent() {
		return $this->_persistent;
	}

	/**
	 * Singleton database connection
	 * 
	 * @param string $server
	 * @param string $port the port of the connection
	 * @param boolean $persistent set if the connection is persistent
	 * 
	 * @return Billrun_Connection
	 */
	public static function getInstance($server = '', $port = '', $persistent = false) {


		if (empty($port)) {
			$server_port = $server;
		} else {
			$server_port = $server . ':' . $port;
		}
		
		settype($persistent, 'boolean');

		if (!isset(self::$instances[$server_port]) || !self::$instances[$server_port]) {
			self::$instances[$server_port] = new static($server_port, $persistent);
		}

		return self::$instances[$server_port];
	}

	protected function __construct($server = '', $persistent = false) {
		$this->_persistent = (bool) $persistent;
		$this->_server = (string) $server;
	}

}