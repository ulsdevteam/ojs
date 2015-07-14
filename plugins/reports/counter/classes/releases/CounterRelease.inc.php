<?php

/**
 * @file plugins/generic/sushiLite/classes/releases/CounterRelease.inc.php
 *
 * Copyright (c) 2014 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class CounterRelease
 * @ingroup plugins_reports_counter
 *
 * @brief A COUNTER report, base class
 */
require_once(dirname(dirname(__FILE__)).'/COUNTER/COUNTER.php');

define('COUNTER_EXCEPTION_WARNING', 0);
define('COUNTER_EXCEPTION_ERROR', 1);
define('COUNTER_EXCEPTION_NO_DATA', 8);
define('COUNTER_EXCEPTION_BAD_COLUMNS', 16);
define('COUNTER_EXCEPTION_BAD_FILTERS', 32);
define('COUNTER_EXCEPTION_BAD_ORDERBY', 64);
define('COUNTER_EXCEPTION_BAD_RANGE', 128);
define('COUNTER_EXCEPTION_INTERNAL', 256);

class CounterRelease {

	var $parentPluginCategory;
	var $parentPluginName;
	
	var $_errors;
	
	/**
	 * Constructor
	 * @param string $parentPluginCategory
	 * @param string $parentPluginName
	 */
	function CounterRelease($parentPluginCategory, $parentPluginName) {
		$this->parentPluginCategory = $parentPluginCategory;
		$this->parentPluginName = $parentPluginName;
	}
	

	/**
	 * Get the parent plugin
	 * @return object
	 */
	function &getParentPlugin() {
		$plugin =& PluginRegistry::getPlugin($this->parentPluginCategory, $this->parentPluginName);
		return $plugin;
	}

	/**
	 * Get the COUNTER Release
	 * @return $string
	 */
	function getRelease() {
		$class = get_class($this);
		if (preg_match('/.+_r[0-9]+(_[0-9]+)?$/', $class)) {
			$parts = explode('_r', $class, 2);
			return str_replace('_', '.', $parts[1]);
		}
		return;
	}

	/**
	 * Get the report code
	 * @return $string
	 */
	function getCode() {
		$class = get_class($this);
		if (preg_match('/.+_r[0-9]+(_[0-9]+)?$/', $class)) {
			$rclass = strrev($class);
			$parts = explode('r_', $rclass, 2);
			return strrev(str_replace('_', '.', $parts[1]));
		}
		return;
	}

	/**
	 * Get the Vendor Id
	 * @return $string
	 */
	function getVendorId() {
		return $this->_getVendorComponent('id');
	}

	/**
	 * Get the Vendor Name
	 * @return $string
	 */
	function getVendorName() {
		return $this->_getVendorComponent('name');
	}

	/**
	 * Get the Vendor Contacts
	 * @return array() COUNTER\Contact
	 */
	function getVendorContacts() {
		return $this->_getVendorComponent('contacts');
	}

	/**
	 * Get the Vendor Website URL
	 * @return string
	 */
	function getVendorWebsiteUrl() {
		return $this->_getVendorComponent('website');
	}

	/**
	 * Get the Vendor Contacts
	 * @return array COUNTER\Contact
	 */
	function getVendorLogoUrl() {
		return $this->_getVendorComponent('logo');
	}

	/**
	 * Get the COUNTER metric type for an Statistics file type
	 * @param $filetype string
	 * @return string
	 */
	function getKeyForFiletype($filetype) {
		switch ($filetype) {
			case STATISTICS_FILE_TYPE_HTML:
				$metricTypeKey = 'ft_html';
				break;
			case STATISTICS_FILE_TYPE_PDF:
				$metricTypeKey = 'ft_pdf';
				break;
			case STATISTICS_FILE_TYPE_OTHER:
			default:
				$metricTypeKey = 'other';
		}
		return $metricTypeKey;
	}
	
	/**
	 * Get the Vendor Componet by key
	 * @param $key string
	 * @return mixed
	 */
	protected function _getVendorComponent($key) {
		$request = PKPApplication::getRequest();
		$site = $request->getSite();
		switch ($key) {
			case 'name':
				return $site->getLocalizedTitle();
			case 'id':
				return $request->getBaseUrl();
			case 'contacts':
				try {
					$contact = new COUNTER\Contact($site->getLocalizedContactName(), $site->getLocalizedContactEmail());
				} catch (Exception $e) {
					$this->setError($e);
					$contact = array();
				}
				return $contact;
			case 'website':
				return $request->getBaseUrl();
			case 'logo':
				return '';
			default:
				return;
		}
	}

	/**
	 * Abstract method must be implemented in the child class
	 * Get the report title
	 * @return $string
	 */
	function getTitle() {
		assert(false);
	}

	/**
	 * Abstract method must be implemented by subclass
	 * @return string COUNTER XML
	 */
	function getMetricsXML($columns = array(), $filters = array(), $orderBy = array(), $range = null) {
		assert(false);
	}

	/**
	 * Get an array of errors
	 * @return array of Exceptions
	 */
	function getErrors() {
		return $this->_errors;
	}

	/**
	 * Set an errors condition; Proper Exception handling is deferred until the OJS 3.0 Release
	 * @param $error Exception
	 */
	function setError($error) {
		if (!$this->_errors) {
			$this->_errors = array();
		}
		array_push($this->_errors, $error);
	}

	/**
	 * Ensure that the $filters do not exceed the current Context
	 * @param array() $filters
	 * @return array()
	 */
	protected function filterForContext($filters) {
		$request = PKPApplication::getRequest();
		$journal = $request->getJournal();
		$journalId = $journal ? $journal->getJournalId() : '';
		// If the request context is at the journal level, the dimension context id must be that same journal id
		if ($journalId) {
			if (isset($filters[STATISTICS_DIMENSION_CONTEXT_ID]) && $filters[STATISTICS_DIMENSION_CONTEXT_ID] != $journalId) {
				$this->setError(new Exception(__('plugins.reports.counter.generic.exception.filter'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_BAD_FILTERS));
			}
			$filters[STATISTICS_DIMENSION_CONTEXT_ID] = $journalId;
			if (isset($filters[STATISTICS_DIMENSION_ASSOC_ID]) && $filters[STATISTICS_DIMENSION_ASSOC_ID] != ASSOC_TYPE_GALLEY) {
				$this->setError(new Exception(__('plugins.reports.counter.exception.filter'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_BAD_FILTERS));
			}
		}
		return $filters;
	}

	/**
	 * Given a Year-Month period and array of COUNTER\PerformanceCounters, create a COUNTER\Metric
	 * @param string $period Date in Ym format
	 * @param array $counters COUNTER\PerformanceCounter array
	 * @return COUNTER\Metric
	 */
	protected function createMetricByMonth($period, $counters) {
		$metric = array();
		try {
			$metric = new COUNTER\Metric(
				// Date range for JR1 is beginning of the month to end of the month
				new COUNTER\DateRange(
					DateTime::createFromFormat('Ymd His', $period.'01 000000'),
					DateTime::createFromFormat('Ymd His', $period.date('t', strtotime(substr($period, 0, 4).'-'.substr($period, 4).'-01')).' 235959')
				),
				'Requests',
				$counters
			);
		} catch (Exception $e) {
			$this->setError($e, COUNTER_EXCEPTION_ERROR | COUNTER_EXCEPTION_INTERNAL);
		}
		return $metric;
	}

}

?>
