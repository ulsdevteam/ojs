<?php

/**
 * @file plugins/generic/htmlArticleGalley/HtmlArticleGalleySettingsForm.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class HtmlArticleGalleySettingsForm
 * @ingroup plugins_generic_htmlArticleGalley
 *
 * @brief Form for journal managers to modify how HTML Article Galleys are presented
 */

import('lib.pkp.classes.form.Form');

class HtmlArticleGalleySettingsForm extends Form {

	/** @var int */
	var $_journalId;

	/** @var object */
	var $_plugin;

	/**
	 * Constructor
	 * @param $plugin HtmlArticleGalleyPlugin
	 * @param $journalId int
	 */
	function __construct($plugin, $journalId) {
		$this->_journalId = $journalId;
		$this->_plugin = $plugin;

		parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

		$this->addCheck(new FormValidatorPost($this));
		$this->addCheck(new FormValidatorCSRF($this));
	}

	/**
	 * Initialize form data.
	 */
	function initData() {
		$this->_data = array(
			'htmlArticleGalleyDisplayType' => $this->_plugin->getSetting($this->_journalId, 'htmlArticleGalleyDisplayType'),
		);
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('htmlArticleGalleyDisplayType'));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('pluginName', $this->_plugin->getName());
		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save settings.
	 */
	function execute(...$functionArgs) {
		$this->_plugin->updateSetting($this->_journalId, 'htmlArticleGalleyDisplayType', $this->getData('htmlArticleGalleyDisplayType'), 'int');
	}
}

