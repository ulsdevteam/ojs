{**
 * plugins/generic/htmlArticleGalley/settingsForm.tpl
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * HTML Article Galley plugin settings
 *
 *}
<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#hagSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="hagSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="hagSettingsFormNotification"}

	{fbvFormArea id="htmlArticleGalleySettingsFormArea" title="plugins.generic.htmlArticleGalley.manager.settings.title"}
		{fbvFormSection for="htmlArticleGalleyDisplayType" list=true description="plugins.generic.htmlArticleGalley.manager.settings.description"}
			{fbvElement type="checkbox" id="htmlArticleGalleyDisplayType" value=HTML_ARTICLE_GALLEY_DISPLAY_INLINE checked=$htmlArticleGalleyDisplayType label="plugins.generic.htmlArticleGalley.manager.settings.htmlArticleGalleyDisplayType"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons}
</form>
