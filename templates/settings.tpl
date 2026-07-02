{**
 * plugins/generic/ojsbrFilenameRename/templates/settings.tpl
 *}
<script>
	$(function() {ldelim}
		$('#ojsbrFilenameRenameSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="ojsbrFilenameRenameSettings"
	method="POST"
	action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="ojsbrFilenameRenameSettingsNotification"}

	<div id="description">{translate key="plugins.generic.ojsbrFilenameRename.settings.description"}</div>

	{fbvFormArea id="ojsbrFilenameRenameSettingsFormArea"}
		{fbvFormSection list=true}
			{fbvElement type="checkbox"
				id="numbersOnly"
				value="1"
				checked=$numbersOnly
				label="plugins.generic.ojsbrFilenameRename.settings.numbersOnly.label"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
