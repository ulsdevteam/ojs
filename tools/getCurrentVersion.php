<?php

/**
 * @file getCurrentVersion.php
 *
*/

require(dirname(__FILE__) . '/bootstrap.inc.php');

import('site.VersionDAO');

class getCurrentVersion extends CommandLineTool {

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Script to report Current Version\n"
			. "Usage: {$this->scriptName}\n";
	}

	/**
	 * Report current version.
	 */
	function execute() {
		$versiondao = new VersionDAO();
		$ver = $versiondao->getCurrentVersion();
		//$hist = $versiondao->getVersionHistory();
		$major = $ver->getMajor();
		$minor = $ver->getMinor();
		$rev   = $ver->getRevision();
		$bld   = $ver->getBuild();
		$inst  = $ver->getDateInstalled();
		$prod  = $ver->getProduct();	
		echo "Version: " . $prod . " " . $major . "." . $minor . "." . $rev . "." . $bld ."\n";
		echo "Installed: " . $inst . "\n";
	}

}

$tool = new getCurrentVersion(isset($argv) ? $argv : array());
$tool->execute();
?>
