<?php

/**
 * @file plugins/generic/htmlArticleGalley/HtmlArticleGalleyPlugin.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HtmlArticleGalleyPlugin
 * @ingroup plugins_generic_htmlArticleGalley
 *
 * @brief Class for HtmlArticleGalley plugin
 */

import('lib.pkp.classes.plugins.GenericPlugin');

define('HTML_ARTICLE_GALLEY_DISPLAY_IFRAME', 0);
define('HTML_ARTICLE_GALLEY_DISPLAY_INLINE', 1);

class HtmlArticleGalleyPlugin extends GenericPlugin {
	/**
	 * @see Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (!parent::register($category, $path, $mainContextId)) return false;
		if ($this->getEnabled($mainContextId)) {
			HookRegistry::register('ArticleHandler::view::galley', array($this, 'articleViewCallback'), HOOK_SEQUENCE_LATE);
			HookRegistry::register('ArticleHandler::download', array($this, 'articleDownloadCallback'), HOOK_SEQUENCE_LATE);
		}
		return true;
	}

	/**
	 * Install default settings on journal creation.
	 * @return string
	 */
	function getContextSpecificPluginSettingsFile() {
		return $this->getPluginPath() . '/settings.xml';
	}

	/**
	 * Get the display name of this plugin.
	 * @return String
	 */
	function getDisplayName() {
		return __('plugins.generic.htmlArticleGalley.displayName');
	}

	/**
	 * Get a description of the plugin.
	 */
	function getDescription() {
		return __('plugins.generic.htmlArticleGalley.description');
	}

	/**
	 * Present the article wrapper page.
	 * @param string $hookName
	 * @param array $args
	 */
	function articleViewCallback($hookName, $args) {
		$request =& $args[0];
		$issue =& $args[1];
		$galley =& $args[2];
		$article =& $args[3];

		if ($galley && $galley->getFileType() == 'text/html') {
			$templateMgr = TemplateManager::getManager($request);
			$htmlArticleGalley = $this->_getHTMLContents($request, $galley);
			$templateMgr->assign(array(
				'issue' => $issue,
				'article' => $article,
				'galley' => $galley,
			));
			$template = 'display.tpl';
			if ($this->getSetting($request->getContext()->getId(), 'htmlArticleGalleyDisplayType') === HTML_ARTICLE_GALLEY_DISPLAY_INLINE) {
				$templateMgr->assign('htmlArticleGalley', $htmlArticleGalley);
				$htmlArticleGalley = $this->_extractBodyContents($htmlArticleGalley);
				$template = 'displayInline.tpl';
			}
			$templateMgr->display($this->getTemplateResource($template));

			return true;
		}

		return false;
	}

	/**
	 * Present rewritten article HTML.
	 * @param string $hookName
	 * @param array $args
	 */
	function articleDownloadCallback($hookName, $args) {
		$article =& $args[0];
		$galley =& $args[1];
		$fileId =& $args[2];
		$request = Application::getRequest();

		if ($galley && $galley->getFileType() == 'text/html' && $galley->getFileId() == $fileId) {
			if (!HookRegistry::call('HtmlArticleGalleyPlugin::articleDownload', array($article,  &$galley, &$fileId))) {
				echo $this->_getHTMLContents($request, $galley);
				$returner = true;
				HookRegistry::call('HtmlArticleGalleyPlugin::articleDownloadFinished', array(&$returner));
			}
			return true;
		}

		return false;
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $verb) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, array('verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic')),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			):array(),
			parent::getActions($request, $verb)
		);
	}

	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		$verb = $request->getUserVar('verb');
		switch ($verb) {
			case 'settings':
				$templateMgr = TemplateManager::getManager();
				$templateMgr->register_function('plugin_url', array($this, 'smartyPluginUrl'));
				$context = $request->getContext();

				$this->import('HtmlArticleGalleySettingsForm');
				$form = new HtmlArticleGalleySettingsForm($this, $context->getId());
				if ($request->getUserVar('save')) {
					$form->readInputData();
					if ($form->validate()) {
						$form->execute();
						return new JSONMessage(true);
					}
				} else {
					$form->initData();
				}
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}

	/**
	 * Return string containing the contents of the HTML file.
	 * This function performs any necessary filtering, like image URL replacement.
	 * @param $request PKPRequest
	 * @param $galley ArticleGalley
	 * @return string
	 */
	function _getHTMLContents($request, $galley) {
		$journal = $request->getJournal();
		$submissionFile = $galley->getFile();
		$contents = file_get_contents($submissionFile->getFilePath());

		// Replace media file references
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		import('lib.pkp.classes.submission.SubmissionFile'); // Constants
		$embeddableFiles = array_merge(
			$submissionFileDao->getLatestRevisions($submissionFile->getSubmissionId(), SUBMISSION_FILE_PROOF),
			$submissionFileDao->getLatestRevisionsByAssocId(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId(), $submissionFile->getSubmissionId(), SUBMISSION_FILE_DEPENDENT)
		);
		$referredArticle = null;
		$articleDao = DAORegistry::getDAO('ArticleDAO');

		foreach ($embeddableFiles as $embeddableFile) {
			$params = array();

			if ($embeddableFile->getFileType()=='text/plain' || $embeddableFile->getFileType()=='text/css') $params['inline']='true';

			// Ensure that the $referredArticle object refers to the article we want
			if (!$referredArticle || $referredArticle->getId() != $galley->getSubmissionId()) {
				$referredArticle = $articleDao->getById($galley->getSubmissionId());
			}
			$fileUrl = $request->url(null, 'article', 'download', array($referredArticle->getBestArticleId(), $galley->getBestGalleyId(), $embeddableFile->getFileId()), $params);
			$pattern = preg_quote(rawurlencode($embeddableFile->getOriginalFileName()));

			$contents = preg_replace(
				'/([Ss][Rr][Cc]|[Hh][Rr][Ee][Ff]|[Dd][Aa][Tt][Aa])\s*=\s*"([^"]*' . $pattern . ')"/',
				'\1="' . $fileUrl . '"',
				$contents
			);

			// Replacement for Flowplayer
			$contents = preg_replace(
				'/[Uu][Rr][Ll]\s*\:\s*\'(' . $pattern . ')\'/',
				'url:\'' . $fileUrl . '\'',
				$contents
			);

			// Replacement for other players (ested with odeo; yahoo and google player won't work w/ OJS URLs, might work for others)
			$contents = preg_replace(
				'/[Uu][Rr][Ll]=([^"]*' . $pattern . ')/',
				'url=' . $fileUrl ,
				$contents
			);

		}

		// Perform replacement for ojs://... URLs
		$contents = preg_replace_callback(
			'/(<[^<>]*")[Oo][Jj][Ss]:\/\/([^"]+)("[^<>]*>)/',
			array($this, '_handleOjsUrl'),
			$contents
		);

		$templateMgr = TemplateManager::getManager($request);
		$contents = $templateMgr->loadHtmlGalleyStyles($contents, $embeddableFiles);

		// Perform variable replacement for journal, issue, site info
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->getByArticleId($galley->getSubmissionId());

		$journal = $request->getJournal();
		$site = $request->getSite();

		$paramArray = array(
			'issueTitle' => $issue?$issue->getIssueIdentification():__('editor.article.scheduleForPublication.toBeAssigned'),
			'journalTitle' => $journal->getLocalizedName(),
			'siteTitle' => $site->getLocalizedTitle(),
			'currentUrl' => $request->getRequestUrl()
		);

		foreach ($paramArray as $key => $value) {
			$contents = str_replace('{$' . $key . '}', $value, $contents);
		}

		return $contents;
	}

	/**
	 * Return string containing the contents of the HTML body
	 * @param $html string
	 * @return string
	 */
	function _extractBodyContents($html) {
		try {
			$errorsEnabled = libxml_use_internal_errors();
			libxml_use_internal_errors(true);
			$dom = DOMDocument::loadHTML($html);
			$tags = $dom->getElementsByTagName('body');
			$bodyContent = '';
			foreach ($tags as $body) {
				foreach ($body->childNodes as $child) {
					$bodyContent .= $dom->saveHTML($child);
				}
				last;
			}
			libxml_use_internal_errors($errorsEnabled);
		} catch (Exception $e) {
			$html = preg_replace('/.*<body[^>]*>/isA', '', $html);
			$html = preg_replace('/<\/body\s*>.*$/isD', '', $html);
			$bodyContent = $html;
		}
		return $bodyContent;
	}
	
	function _handleOjsUrl($matchArray) {
		$request = Application::getRequest();
		$url = $matchArray[2];
		$anchor = null;
		if (($i = strpos($url, '#')) !== false) {
			$anchor = substr($url, $i+1);
			$url = substr($url, 0, $i);
		}
		$urlParts = explode('/', $url);
		if (isset($urlParts[0])) switch(strtolower_codesafe($urlParts[0])) {
			case 'journal':
				$url = $request->url(
				isset($urlParts[1]) ?
				$urlParts[1] :
				$request->getRequestedJournalPath(),
				null,
				null,
				null,
				null,
				$anchor
				);
				break;
			case 'article':
				if (isset($urlParts[1])) {
					$url = $request->url(
							null,
							'article',
							'view',
							$urlParts[1],
							null,
							$anchor
					);
				}
				break;
			case 'issue':
				if (isset($urlParts[1])) {
					$url = $request->url(
							null,
							'issue',
							'view',
							$urlParts[1],
							null,
							$anchor
					);
				} else {
					$url = $request->url(
							null,
							'issue',
							'current',
							null,
							null,
							$anchor
					);
				}
				break;
			case 'sitepublic':
				array_shift($urlParts);
				import ('classes.file.PublicFileManager');
				$publicFileManager = new PublicFileManager();
				$url = $request->getBaseUrl() . '/' . $publicFileManager->getSiteFilesPath() . '/' . implode('/', $urlParts) . ($anchor?'#' . $anchor:'');
				break;
			case 'public':
				array_shift($urlParts);
				$journal = $request->getJournal();
				import ('classes.file.PublicFileManager');
				$publicFileManager = new PublicFileManager();
				$url = $request->getBaseUrl() . '/' . $publicFileManager->getJournalFilesPath($journal->getId()) . '/' . implode('/', $urlParts) . ($anchor?'#' . $anchor:'');
				break;
		}
		return $matchArray[1] . $url . $matchArray[3];
	}
}
