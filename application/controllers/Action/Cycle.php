<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class CycleAction extends Action_Base {

	protected $billingCycleCol = null;

	/**
	 * Build the options for the aggregator
	 * @return boolean
	 * @todo This is a generic function, might be better to create a basic action class
	 * that uses an aggregator and have all these functions in it.
	 */
	protected function buildOptions() {
		$possibleOptions = array(
			'type' => false,
			'stamp' => true,
			'page' => true,
			'size' => true,
			'fetchonly' => true,
		);

		$options = $this->_controller->getInstanceOptions($possibleOptions);
		if ($options === false) {
			return false;
		}

		if (is_null($options['stamp'])) {
			$nextBillrunKey = Billrun_Util::getBillrunKey(time());
			$currentBillrunKey = Billrun_Util::getPreviousBillrunKey($nextBillrunKey);
			$options['stamp'] = $currentBillrunKey;
		}

		if (!isset($options['size']) || !$options['size']) {
			// default value for size
			$options['size'] = Billrun_Factory::config()->getConfigValue('customer.aggregator.size');
		}
		
		return $options;
	}
	
	/**
	 * Get the process interval
	 * @return int
	 */
	protected function getProcessInterval() {
		$processInterval = (int) Billrun_Factory::config()->getConfigValue('cycle.processes.interval');
		if (Billrun_Factory::config()->isProd()) {
			if ($processInterval < 60) {   // 1 minute is minimum sleep time 
				$processInterval = 60;
			}
		}
		return $processInterval;
	}
	
	/**
	 * method to execute the aggregate process
	 * it's called automatically by the cli main controller
	 */
	public function execute() {
		$options = $this->buildOptions();
		$this->billingCycleCol = Billrun_Factory::db()->billing_cycleCollection();
		$processInterval = $this->getProcessInterval();

		$stamp = $options['stamp'];
		$size = (int)$options['size'];
		
		$isCycleOver = false;
		
		$zeroPages = Billrun_Factory::config()->getConfigValue('customer.aggregator.zero_pages_limit');
				
		while($isCycleOver != true) {
			$pid = pcntl_fork();
			if ($pid == -1) {
				die('could not fork');
			}
			
			$this->_controller->addOutput("Running on PID " . $pid);
		
			// Parent process.
			if ($pid) {
				$this->executeParentProcess($processInterval);
				$isCycleOver = Billrun_Aggregator_Customer::isBillingCycleOver($this->billingCycleCol, $stamp, $size, $zeroPages);
				continue;
			}
			
			// Child process
			$this->executeChildProcess($options);
			break;
		}
	}

	protected function executeParentProcess($processInterval) {
		$this->_controller->addOutput("Going to sleep for " . $processInterval);
		sleep($processInterval);
		pcntl_signal(SIGCHLD, SIG_IGN);
	}
	
	/**
	 * Execute the child process logic
	 * @param type $options
	 * @return type
	 */
	protected function executeChildProcess($options) {
		$aggregator = $this->getAggregator($options);
		if($aggregator == false) {
			return;
		}
		
		$this->_controller->addOutput("Loading data to Aggregate...");
		$aggregator->load();
		if (isset($options['fetchonly'])) {
			$this->_controller->addOutput("Only fetched aggregate accounts info. Exit...");
			return;
		}

		$this->_controller->addOutput("Starting to Aggregate. This action can take a while...");
		$aggregator->aggregate();
		$this->_controller->addOutput("Finish to Aggregate.");
	}
	
	/**
	 * Get an aggregator with input options
	 * @param array $options - Array of options to initialize the aggregator with.
	 * @return Aggregator
	 * @todo getAggregator might be common in actions, maybe create a basic aggregate action class?
	 */
	protected function getAggregator($options) {
		$this->_controller->addOutput("Loading aggregator");
		$aggregator = Billrun_Aggregator::getInstance($options);
		
		if(!$aggregator || !$aggregator->isValid()) {
			$this->_controller->addOutput("Aggregator cannot be loaded");
			return false;
		}
		
		$this->_controller->addOutput("Aggregator loaded");
		return $aggregator;
	}
}
