<?php

/**
 * @file pages/manager/FilesHandler.inc.php
 *
 * Copyright (c) 2013-2017 Simon Fraser University
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FilesHandler
 * @ingroup pages_manager
 *
 * @brief Handle requests for files browser functions.
 */

import('pages.manager.ManagerHandler');

class FilesHandler extends ManagerHandler {
	
	/**
	 * @var $locationOptions array()
	 *  This array lists possible path prefixes, and the associated config keys
	 */
	var $locationOptions = array('public' => array('config' => 'public_files_dir', 'locale' => 'manager.files.publicFilesDir'), 'private' => array('config' => 'files_dir', 'locale' => 'manager.files.filesDir'));
	/**
	 * Constructor
	 */
	function FilesHandler() {
		parent::ManagerHandler();
	}

	/**
	 * Display the files associated with a journal.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function files($args, &$request) {
		$this->validate();
		
		$location = '';
		if (count($args)) {
			$location = $this->_validateLocation($args);
		}
		$this->setupTemplate(true);

		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('pageHierarchy', array(array($request->url(null, 'manager'), 'manager.journalManagement')));

		$this->_parseDirArg($args, $currentDir, $parentDir);
		if ($location) {
			$currentPath = $this->_getRealFilesDir($location, $request, $currentDir);
		} else {
			$currentDir = '';
			$parentDir = '';
			$currentPath = NULL;
		}

		if ($location && @is_file($currentPath)) {
			if ($request->getUserVar('download')) {
				$fileManager->downloadFile($currentPath);
			} else {
				$fileManager->downloadFile($currentPath, $this->_fileMimeType($currentPath), true);
			}

		} else {
			$files = array();
			if (!$location) {
				foreach ($this->locationOptions as $k => $v) {
					$info = array(
						'name' => $k,
						'isDir' => true,
						'mimetype' => AppLocale::Translate($v['locale']),
						'mtime' => filemtime($this->_getRealFilesDir($k, $request, '')),
						'size' => '',
					);
					$files[$k] = $info;
				}
			} elseif ($dh = @opendir($currentPath)) {
				while (($file = readdir($dh)) !== false) {
					if ($file != '.' && $file != '..') {
						$filePath = $currentPath . '/'. $file;
						$isDir = is_dir($filePath);
						$info = array(
							'name' => $file,
							'isDir' => $isDir,
							'mimetype' => $isDir ? '' : $this->_fileMimeType($filePath),
							'mtime' => filemtime($filePath),
							'size' => $isDir ? '' : $fileManager->getNiceFileSize(filesize($filePath)),
						);
						if ($location == 'public' && !$isDir) {
							$dir = $this->_getRealFilesSubdir($request, $currentDir);
							$dir .= substr($dir, -1) == '/' ? '' : '/';
							$info['permalink'] = $request->getBasePath().'/public'.$dir.$file;
						}
						$files[$file] = $info;
					}
				}
				closedir($dh);
			}
			ksort($files);
			$templateMgr->assign_by_ref('files', $files);
			$templateMgr->assign('currentDir', ($location ? $location.($currentDir ? '/' : '') : '').$currentDir);
			$templateMgr->assign('parentDir', ($parentDir ? $location.'/'.$parentDir : ($currentDir ? $location : '')));
			$templateMgr->assign('helpTopicId','journal.managementPages.fileBrowser');
			$templateMgr->display('manager/files/index.tpl');
		}
	}

	/**
	 * Upload a new file.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function fileUpload($args, &$request) {
		$this->validate();
		$location = $this->_validateLocation($args);

		$this->_parseDirArg($args, $currentDir, $parentDir);
		$currentPath = $this->_getRealFilesDir($location, $request, $currentDir);

		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		if ($fileManager->uploadedFileExists('file')) {
			$destPath = $currentPath . '/' . $this->_cleanFileName($fileManager->getUploadedFileName('file'));
			@$fileManager->uploadFile('file', $destPath);
		}

		$request->redirect(null, null, 'files', explode('/', $location.'/'.$currentDir));
	}

	/**
	 * Create a new directory
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function fileMakeDir($args, &$request) {
		$this->validate();
		$location = $this->_validateLocation($args);

		$this->_parseDirArg($args, $currentDir, $parentDir);

		if ($dirName = $request->getUserVar('dirName')) {
			$currentPath = $this->_getRealFilesDir($location, $request, $currentDir);
			$newDir = $currentPath . '/' . $this->_cleanFileName($dirName);

			import('lib.pkp.classes.file.FileManager');
			$fileManager = new FileManager();
			@$fileManager->mkdir($newDir);
		}

		$request->redirect(null, null, 'files', explode('/', $location.'/'.$currentDir));
	}

	/**
	 * Delete a file.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function fileDelete($args, &$request) {
		$this->validate();
		$location = $this->_validateLocation($args);

		$this->_parseDirArg($args, $currentDir, $parentDir);
		$currentPath = $this->_getRealFilesDir($location, $request, $currentDir);

		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();

		if (@is_file($currentPath)) {
			$fileManager->deleteFile($currentPath);
		} else {
			// TODO Use recursive delete (rmtree) instead?
			@$fileManager->rmdir($currentPath);
		}

		$request->redirect(null, null, 'files', explode('/', $location.'/'.$parentDir));
	}


	//
	// Helper functions
	// FIXME Move some of these functions into common class (FileManager?)
	//
	/**
	 * Validates that a location exists in the locationOptions.
	 * @param $args array
	 * @return $location string
	 * Side Effect: redirect to files root if location is invalid
	 */
	function _validateLocation(&$args) {
		$location = array_shift($args);
		if ($location && $this->locationOptions[$location]) {
			return $location;
		}
		Request::redirect(null, null, 'files');
	}

	function _parseDirArg($args, &$currentDir, &$parentDir) {
		$pathArray = array_filter($args, array($this, '_fileNameFilter'));
		$currentDir = join($pathArray, '/');
		array_pop($pathArray);
		$parentDir = join($pathArray, '/');
	}

	function _getRealFilesDir($location, $request, $currentDir) {
		return Config::getVar('files', $this->locationOptions[$location]['config']) . $this->_getRealFilesSubdir($request, $currentDir);
	}
	
	function _getRealFilesSubdir($request, $currentDir) {
		$journal =& $request->getJournal();
		return '/journals/' . $journal->getId() .'/' . $currentDir;
	}

	function _fileNameFilter($var) {
		return (!empty($var) && $var != '..' && $var != '.' && strpos($var, '/')===false);
	}

	function _cleanFileName($var) {
		$var = String::regexp_replace('/[^\w\-\.]/', '', $var);
		if (!$this->_fileNameFilter($var)) {
			$var = time() . '';
		}
		return $var;
	}

	function _fileMimeType($filePath) {
		return String::mime_content_type($filePath);
	}
}

?>
