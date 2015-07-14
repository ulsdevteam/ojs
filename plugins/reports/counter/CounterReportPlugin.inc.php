<?php

/**
 * @file plugins/reports/counter/CounterReportPlugin.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CounterReportPlugin
 * @ingroup plugins_reports_counter
 *
 * @brief Counter report plugin
 */

define('OJS_METRIC_TYPE_LEGACY_COUNTER', 'ojs::legacyCounterPlugin');

define('COUNTER_CLASS_SUFFIX', '.inc.php');

import('classes.plugins.ReportPlugin');

class CounterReportPlugin extends ReportPlugin {

	/**
	 * @see PKPPlugin::register($category, $path)
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);

		if($success) {
			$this->addLocaleData();
		}
		return $success;
	}

	/**
	 * @see PKPPlugin::getLocaleFilename($locale)
	 */
	function getLocaleFilename($locale) {
		$localeFilenames = parent::getLocaleFilename($locale);

		// Add dynamic locale keys.
		foreach (glob($this->getPluginPath() . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . '*.xml') as $file) {
			if (!in_array($file, $localeFilenames)) {
				$localeFilenames[] = $file;
			}
		}

		return $localeFilenames;
	}

	/**
	 * @see PKPPlugin::getName()
	 */
	function getName() {
		return 'CounterReportPlugin';
	}

	/**
	 * @see PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.reports.counter');
	}

	/**
	 * @see PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.reports.counter.description');
	}

	/**
	 * @see PKPPlugin::getTemplatePath()
	 */
	function getTemplatePath() {
		return parent::getTemplatePath() . 'templates/';
	}	 


	/**
	 * List the valid COUNTER releases
	 * Must exist in the release path as CounterRelease{version}.inc.php. If version is M_m, this will become M.m
	 * @return array
	 */
	function getValidReleases() {
		$releases = array();
		$prefix = $this->getReleasePath().DIRECTORY_SEPARATOR.'CounterRelease';
		$suffix = COUNTER_CLASS_SUFFIX;
		foreach (glob($prefix.'*'.$suffix) as $file) {
			if ($file == $prefix.$suffix) {
				continue;
			}
			$releases[] = str_replace('_', '.', substr($file, strlen($prefix), -strlen($suffix)));
		}
		return $releases;
	}

	/**
	 * Get the latest counter release
	 * Must exist in the release path as CounterRelease{version}.inc.php
	 * @return string
	 */
	function getLatestRelease() {
		$releases = $this->getValidReleases();
		sort($releases);
		return array_pop($releases);
	}
	
	/**
	 * List the valid reports
	 * Must exist in the report path as {Report}_r{release}.inc.php
	 * @return array multidimentional array release => array( report => reportClassName )
	 */
	function getValidReports() {
		$reports = array();
		$prefix = $this->getReportPath().DIRECTORY_SEPARATOR;
		foreach ($this->getValidReleases() as $release) {
			$suffix = '_r'.str_replace('.', '_', $release).COUNTER_CLASS_SUFFIX;
			foreach (glob($prefix.'*'.$suffix) as $file) {
				$reports[$release][substr($file, strlen($prefix), -strlen($suffix))] = substr($file, strlen($prefix), -strlen(COUNTER_CLASS_SUFFIX));
			}
		}
		return $reports;
	}

	/**
	 * Get a COUNTER Reporter Object
	 * Must exist in the report path as {Report}_r{release}.inc.php
	 * @return object
	 */
	function getReporter($report, $release) {
		if (strpos($report, '.') !== FALSE) {
			$report = str_replace('.', '_', $report);
		}
		if (strpos($release, '.') !== FALSE) {
			$release = str_replace('.', '_', $release);
		}
		$reportClass = $report.'_r'.$release;
		$reportClasspath = 'plugins.reports.counter.classes.reports.';
		$reportPath = str_replace('.', DIRECTORY_SEPARATOR, $reportClasspath);
		if (file_exists($reportPath.$reportClass.'.inc.php')) {
			import($reportPath.$reportClass);
			$reporter = new $reportClass('reports', $this->getName());
			return $reporter;
		}
		return false;
	}

	/**
	 * Get classes path for this plugin.
	 * @return string Path to plugin's classes
	 */
	function getClassPath() {
		return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'classes';
	}
	

	/**
	 * Return the report path
	 * @return string
	 */
	function getReportPath() {
		return $this->getClassPath().DIRECTORY_SEPARATOR.'reports';
	}

	/**
	 * Return the report path
	 * @return string
	 */
	function getReleasePath() {
		return $this->getClassPath().DIRECTORY_SEPARATOR.'releases';
	}

	/**
	 * @see PKPPlugin::isSitePlugin()
	 */
	function isSitePlugin() {
		return true;
	}

	/**
	 * @see ReportPlugin::setBreadcrumbs()
	 */
	function setBreadcrumbs() {
		$templateMgr =& TemplateManager::getManager();
		$pageCrumbs = array(
			array(
				Request::url(null, 'user'),
				'navigation.user'
			),
			array(
				Request::url(null, 'manager'),
				'user.role.manager'
			),
			array(
				Request::url(null, 'manager', 'statistics'),
				'manager.statistics'
			)
		);

		$templateMgr->assign('pageHierarchy', $pageCrumbs);
	}

	/**
	 * @see ReportPlugin::display()
	 */
	function display(&$args, &$request) {
		parent::display($args, $request);
		// We need these constants
		import('classes.statistics.StatisticsHelper');

		if (!Validation::isSiteAdmin()) {
			Validation::redirectLogin();
		}

		$this->setBreadcrumbs();
		$available = $this->getValidReports();
		$years = $this->_getYears();
		if ($request->getUserVar('type')) {
			$type = (string) $request->getUserVar('type');
			$errormessage = '';
			switch ($type) {
				case 'report':
				case 'reportxml':
					// Legacy COUNTER Release 3
					import('plugins.reports.counter.classes.reports.JR1_r3_0');
					$r3jr1 = new JR1_r3_0('reports', $this->getName());
					$r3jr1->_display($request);
					return;
				case 'fetch':
					// Modern COUNTER Releases
					// must provide a release, report, and year parameter
					if ($request->getUserVar('release') && $request->getUserVar('report') && $request->getUserVar('year')) {
						// release, report and year parameters must be sane
						if (isset($available[$request->getUserVar('release')][$request->getUserVar('report')]) && in_array($request->getUserVar('year'), $years)) {
							// try to get the report
							$reporter = $this->getReporter($request->getUserVar('report'), $request->getUserVar('release'));
							if ($reporter) {
								// default report parameters with a yearlong range
								$xmlResult = $reporter->getMetricsXML(array(), array(STATISTICS_DIMENSION_MONTH => array('from' => $request->getUserVar('year').'01', 'to' => $request->getUserVar('year').'12')));
								if ($xmlResult) {
									header('content-type: text/xml');
									header('content-disposition: attachment; filename=counter-'.$request->getUserVar('release') . '-' . $request->getUserVar('report') . '-' . date('Ymd') . '.xml');
									print $xmlResult;
									return;
								} else {
									$errormessage = __('plugins.reports.counter.error.noResults');
								}
							}
						}
					}
					// fall through to default case with error message
					if (!$errormessage) {
						$errormessage = __('plugins.reports.counter.error.badParameters');
					}
				default:
					if (!$errormessage) {
						$errormessage = __('plugins.reports.counter.error.badRequest');
					}
					$user =& Request::getUser();
					import('classes.notification.NotificationManager');
					$notificationManager = new NotificationManager();
					$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $errormessage));
			}
		}
		$legacyYears = $this->_getYears(true);
		$templateManager =& TemplateManager::getManager();
		// Legacy version 3 is only available via reportxml method
		unset($available['3.0']);
		krsort($available);
		$templateManager->assign('available', $available);
		$templateManager->assign('years', $years);
		if (!empty($legacyYears)) $templateManager->assign('legacyYears', $legacyYears);
		$templateManager->display($this->getTemplatePath() . 'index.tpl');
	}

	/**
	* Get the years for which log entries exist in the DB.
	* @param $useLegacyStats boolean Use the old counter plugin data.
	* @return array
	*/
	function _getYears($useLegacyStats = false) {
		if ($useLegacyStats) {
			$metricType = OJS_METRIC_TYPE_LEGACY_COUNTER;
			$filter = array();
		} else {
			$metricType = OJS_METRIC_TYPE_COUNTER;
			$filter = array(STATISTICS_DIMENSION_ASSOC_TYPE => ASSOC_TYPE_GALLEY);
		}
		$metricsDao =& DAORegistry::getDAO('MetricsDAO'); /* @var $metricsDao MetricsDAO */
		$results = $metricsDao->getMetrics($metricType, array(STATISTICS_DIMENSION_MONTH), $filter);
		$years = array();
		foreach($results as $record) {
			$year = substr($record['month'], 0, 4);
			if (in_array($year, $years)) continue;
			$years[] = $year;
		}

		return $years;
	}

}

?>
