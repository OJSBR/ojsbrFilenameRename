{**
 * plugins/generic/ojsbrFilenameRename/templates/settings.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Per-journal settings form.
 *}
<script>
	$(function() {ldelim}
		$('#ojsbrFilenameRenameSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="ojsbrFilenameRenameSettings" method="POST" action="{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="ojsbrFilenameRenameSettingsNotification"}

	<p class="description">{translate key="plugins.generic.ojsbrFilenameRename.settings.description"}</p>

	{fbvFormArea id="ojsbrFilenameRenameFormatArea"}
		{fbvFormSection list=true title="plugins.generic.ojsbrFilenameRename.settings.format"}
			{fbvElement type="radio" id="ojsbrFilenameRenameNumbersOnly0" name="numbersOnly" value="0" checked=($numbersOnly === "0") label=$labelDescriptive translate=false}
			{fbvElement type="radio" id="ojsbrFilenameRenameNumbersOnly1" name="numbersOnly" value="1" checked=($numbersOnly === "1") label=$labelNumbersOnly translate=false}
		{/fbvFormSection}
		{fbvFormSection list=true title="plugins.generic.ojsbrFilenameRename.settings.language"}
			{fbvElement type="radio" id="ojsbrFilenameRenameLocaleUser" name="filenameLocale" value="user" checked=($filenameLocale === "user") label=$labelLocaleUser translate=false}
			{fbvElement type="radio" id="ojsbrFilenameRenameLocaleContext" name="filenameLocale" value="context" checked=($filenameLocale === "context") label=$labelLocaleContext translate=false}
		{/fbvFormSection}
		<p class="pkp_help">{translate key="plugins.generic.ojsbrFilenameRename.settings.language.description"}</p>
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
