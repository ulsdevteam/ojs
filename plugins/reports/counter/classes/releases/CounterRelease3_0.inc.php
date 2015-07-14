<?php

/**
 * @file plugins/generic/sushiLite/classes/releases/CounterRelease3_0.inc.php
 *
 * Copyright (c) 2014 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class CounterRelease3_0
 * @ingroup plugins_reports_counter
 *
 * @brief A COUNTER report, release 3.0
 */

import('plugins.reports.counter.classes.releases.CounterRelease');

class CounterRelease3_0 extends CounterRelease {

	/**
	 * Constructor
	 * @param string $parentPluginCategory
	 * @param string $parentPluginName
	 */
	function CounterRelease3_0($parentPluginCategory, $parentPluginName) {
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
		// Release 3.0 support only JR1; getMetricsXML() is implemented in that class
		assert(false);
	}


}

?>
