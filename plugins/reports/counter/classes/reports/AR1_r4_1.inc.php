<?php

/**
 * @file plugins/generic/sushiLite/classes/reports/AR1_r4_1.inc.php
 *
 * Copyright (c) 2014 University of Pittsburgh
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class AR1_r4_1
 * @ingroup plugins_reports_counter
 *
 * @brief Article Report 1, release 4.1
 */

import('plugins.reports.counter.classes.releases.CounterRelease4_1');

class AR1_r4_1 extends CounterRelease4_1 {
	
	/**
	 * Constructor
	 */
	function AR1_r4_1($parentPluginCategory, $parentPluginName) {
		parent::CounterRelease4_1($parentPluginCategory, $parentPluginName);
	}

	/**
	 * Get the report title
	 * @return $string
	 */
	function getTitle() {
		return __('plugins.reports.counter.ar1.title');
	}

	/*
	 * Convert an OJS metrics request to a COUNTER ReportItem
	 * @param $columns string|array column (aggregation level) selection
	 * @param $filters array report-level filter selection
	 * @param $orderBy array order criteria
	 * @param $range null|DBResultRange paging specification
	 * @see ReportPlugin::getMetrics for more details
	 * @return object COUNTER\ReportItem
	 */
	function getMetrics($columns = array(), $filters = array(), $orderBy = array(), $range = null) {
		$metricsDao =& DAORegistry::getDAO('MetricsDAO');
		
		// Columns are fixed for this report
		$defaultColumns = array(STATISTICS_DIMENSION_MONTH, STATISTICS_DIMENSION_SUBMISSION_ID);
		if ($columns && array_diff($columns, $defaultColumns)) {
			$this->setError(new Exception(__('plugins.reports.counter.exception.column'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_BAD_COLUMNS));
		}
		// Check filters for correct context(s)
		$validFilters = $this->filterForContext($filters);
		// Filters defaults to last month, but can be provided by month or by day
		if (!isset($filters[STATISTICS_DIMENSION_MONTH]) && !isset($filters[STATISTICS_DIMENSION_DAY])) {
			$validFilters[STATISTICS_DIMENSION_MONTH] = array(
				'from' => date_format(date_create("first day of previous month"), 'Ymd'),
				'to' => date_format(date_create("last day of previous month"), 'Ymd')
				);
		} elseif (isset($filters[STATISTICS_DIMENSION_MONTH])) {
			$validFilters[STATISTICS_DIMENSION_MONTH] = $filters[STATISTICS_DIMENSION_MONTH];
			unset($filters[STATISTICS_DIMENSION_MONTH]);
		} elseif (isset($filters[STATISTICS_DIMENSION_DAY])) {
			$validFilters[STATISTICS_DIMENSION_DAY] = $filters[STATISTICS_DIMENSION_DAY];
			unset($filters[STATISTICS_DIMENSION_DAY]);
		}
		if (!isset($filters[STATISTICS_DIMENSION_ASSOC_TYPE])) {
			$validFilters[STATISTICS_DIMENSION_ASSOC_TYPE] = ASSOC_TYPE_GALLEY;
			unset($filters[STATISTICS_DIMENSION_ASSOC_TYPE]);
		} elseif ($filters[STATISTICS_DIMENSION_ASSOC_TYPE] != ASSOC_TYPE_GALLEY) {
			$this->setError(new Exception(__('plugins.reports.counter.exception.filter'), COUNTER_EXCEPTION_ERROR | COUNTER_EXCEPTION_BAD_FILTERS));
		}
		// AR1 could be filtered to the Journal, Issue, or Article level
		foreach ($filters as $key => $filter) {
			switch ($key) {
				case STATISTICS_DIMENSION_CONTEXT_ID:
				case STATISTICS_DIMENSION_ISSUE_ID:
				case STATISTICS_DIMENSION_SUBMISSION_ID:
					$validFilters[$key] = $filter;
					unset($filters[$key]);
			}
		}
		// Unrecognized filters raise an error
		if (array_keys($filters)) {
			$this->setError(new Exception(__('plugins.reports.counter.exception.filter'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_BAD_FILTERS));
		}
		// Metric type is ojs::counter
		$metricType = OJS_METRIC_TYPE_COUNTER;
		// Ordering must be by Journal (ReportItem), and by Month (ItemPerformance) for JR1
		$validOrder = array(STATISTICS_DIMENSION_SUBMISSION_ID => STATISTICS_ORDER_DESC, STATISTICS_DIMENSION_MONTH => STATISTICS_ORDER_ASC);
		// TODO: range
		$results = $metricsDao->getMetrics($metricType, $defaultColumns, $validFilters, $validOrder);
		$reportItems = array();
		if ($results) {
			// We'll create a new Report Item with these Metrics on a article change
			$metrics = array();
			$lastArticle = 0;
			foreach ($results as $rs) {
				// Article changes trigger a new ReportItem
				if ($lastArticle != $rs[STATISTICS_DIMENSION_SUBMISSION_ID]) {
					if ($lastArticle != 0 && $metrics) {
						$item = $this->_createReportItem($lastArticle, $metrics);
						if ($item) {
							$reportItems[] = $item;
						} else {
							$this->setError(new Exception(__('plugins.reports.counter.exception.partialData'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_PARTIAL_DATA));
						}
						$metrics = array();
					}
				}
				$metrics[] = $this->createMetricByMonth($rs[STATISTICS_DIMENSION_MONTH], array(new COUNTER\PerformanceCounter('ft_total', $rs[STATISTICS_METRIC])));
				$lastArticle = $rs[STATISTICS_DIMENSION_SUBMISSION_ID];
			}
			// Capture the last unprocessed ItemPerformance and ReportItem entries, if applicable
			if ($metrics) {
				$item = $this->_createReportItem($lastArticle, $metrics);
				if ($item) {
					$reportItems[] = $item;
				} else {
					$this->setError(new Exception(__('plugins.reports.counter.exception.partialData'), COUNTER_EXCEPTION_WARNING | COUNTER_EXCEPTION_PARTIAL_DATA));
				}
			}
		} else {
			$this->setError(new Exception(__('plugins.reports.counter.exception.noData'), COUNTER_EXCEPTION_ERROR | COUNTER_EXCEPTION_NO_DATA));
		}
		return $reportItems;
	}

	/**
	 * Given a submissionId and an array of COUNTER\Metrics, return a COUNTER\ReportItems
	 * @param int $submissionId
	 * @param array $metrics COUNTER\Metric array
	 * @return mixed COUNTER\ReportItems or false
	 */
	private function _createReportItem($submissionId, $metrics) {
		$articleDao = DAORegistry::getDAO('ArticleDAO');
		$article = $articleDao->getArticle($submissionId);
		if (!$article) {
			return false;
		}
		$title = $article->getLocalizedTitle();
		$journalId = $article->getJournalId();
		$journalDao = DAORegistry::getDAO('JournalDAO');
		$journal = $journalDao->getById($journalId);
		if (!$journal) {
			return false;
		}
		$journalName = $journal->getJournalTitle();
		$journalPubIds = array();
		foreach (array('print', 'online') as $issnType) {
			if ($journal->getSetting($issnType.'Issn')) {
				try {
					$journalPubIds[] = new COUNTER\Identifier(ucfirst($issnType).'_ISSN', $journal->getSetting($issnType.'Issn'));
				} catch (Exception $ex) {
					// Just ignore it
				}
			}
		}
		$journalPubIds[] = new COUNTER\Identifier(COUNTER41_LITERAL_PROPRIETARY, $journal->getPath());
		$pubIdPlugins =& PluginRegistry::loadCategory('pubIds', true, $journalId);
		$articlePubIds = array();
		$articlePubIds[] = new COUNTER\Identifier(COUNTER41_LITERAL_PROPRIETARY, $submissionId);
		if ($pubIdPlugins) {
			foreach ($pubIdPlugins as $pubIdPlugin) {
				$pubId = $pubIdPlugin->getPubId($article, true);
				if ($pubId) {
					switch ($pubIdPlugin->getPubIdType()) {
						case 'doi':
							try {
								$articlePubIds[] = new COUNTER\Identifier(strtoupper($pubIdPlugin->getPubIdType()), $pubId);
							} catch (Exception $ex) {
								// Just ignore it
							}
							break;
						default:
					}
				}
			}
		}
		$reportItem = array();
		try {
			$reportItem = new COUNTER\ReportItems(
				__('common.openJournalSystems'),
				$title,
				COUNTER41_LITERAL_ARTICLE,
				$metrics,
				new COUNTER\ParentItem($journalName, COUNTER41_LITERAL_JOURNAL, $journalPubIds),
				$articlePubIds
			);
		} catch (Exception $e) {
			$this->setError($e, COUNTER_EXCEPTION_ERROR | COUNTER_EXCEPTION_INTERNAL);
		}
		return $reportItem;
	}

}

?>
