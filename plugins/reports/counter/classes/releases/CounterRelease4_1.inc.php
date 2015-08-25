<?php

/**
 * @file plugins/generic/sushiLite/classes/releases/CounterRelease4_1.inc.php
 *
 * Copyright (c) 2014 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class CounterRelease4_1
 * @ingroup plugins_reports_counter
 *
 * @brief A COUNTER report, release 4.1
 */
import('plugins.reports.counter.classes.releases.CounterRelease');
// COUNTER as of 4.1 is not internationalized and requires English constants
define('COUNTER41_LITERAL_ARTICLE', 'Article');
define('COUNTER41_LITERAL_JOURNAL', 'Journal');
define('COUNTER41_LITERAL_PROPRIETARY', 'Proprietary');

class CounterRelease4_1 extends CounterRelease {

	/**
	 * Constructor
	 * @param string $parentPluginCategory
	 * @param string $parentPluginName
	 */
	function CounterRelease4_1($parentPluginCategory, $parentPluginName) {
		parent::CounterRelease($parentPluginCategory, $parentPluginName);
	}
	
	/**
	 * Construct a Reports result containing the provided performance metrics
	 * @param $columns string|array column (aggregation level) selection
	 * @param $filters array report-level filter selection
	 * @param $orderBy array order criteria
	 * @param $range null|DBResultRange paging specification
	 * @see ReportPlugin::getMetrics for more details
	 * @return string xml
	 */
	function getMetricsXML($columns = array(), $filters = array(), $orderBy = array(), $range = null) {
		$reportItems = $this->getMetrics($columns, $filters, $orderBy, $range);
		$errors = $this->getErrors();
		$fatal = false;
		foreach ($errors as $error) {
			if ($error->getCode() & COUNTER_EXCEPTION_ERROR) {
				$fatal = true;
			}
		}
		if (!$fatal) {
			try {
				$report = new COUNTER\Report(
					String::generateUUID(),
					$this->getRelease(),
					$this->getCode(),
					$this->getTitle(),
					new COUNTER\Customer(
						'0', // customer id is unused
						$reportItems,
						__('plugins.reports.counter.allCustomers')
					),
					new COUNTER\Vendor(
						$this->getVendorID(),
						$this->getVendorName(),
						$this->getVendorContacts(),
						$this->getVendorWebsiteUrl(),
						$this->getVendorLogoUrl()
					)
				);
			} catch (Exception $e) {
				$this->setError($e, COUNTER_EXCEPTION_ERROR | COUNTER_EXCEPTION_INTERNAL);
			}
			if (isset($report)) {
				return (string) $report;
			}
		}
		return;
	}

}

?>
