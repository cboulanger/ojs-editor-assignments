{**
 * plugins/generic/editorAssignments/templates/transfer.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Transfer a submission to another editor, or assign an editor ($editor null).
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<h1 class="app__pageHeading">{$pageTitle|escape}</h1>
<div class="app__contentPanel erPanel" v-pre>
	<p><a href="{url page=$erPage anchor="editor-`$editor.id|default:0`"}">← {translate key="plugins.generic.editorAssignments.title"}</a></p>
	<form method="post" action="{url page=$erPage op="saveTransfer"}" class="erForm">
		{csrf}
		<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
		<input type="hidden" name="editorId" value="{$editor.id|default:0|escape}" />
		<div class="erField">
			<span class="erLabel">{translate key="submission.submission"}</span>
			#{$submissionId|escape} {if $authors}{$authors|escape}: {/if}{$submissionTitle|escape}
		</div>
		{if $editor}
			<div class="erField">
				<span class="erLabel">{translate key="plugins.generic.editorAssignments.transfer.from"}</span>
				{$editor.name|escape}
			</div>
		{/if}
		<div class="erField">
			<label class="erLabel" for="erNewEditor">{if $editor}{translate key="plugins.generic.editorAssignments.transfer.to"}{else}{translate key="plugins.generic.editorAssignments.editor"}{/if}</label>
			{if $candidates}
				<select id="erNewEditor" name="newEditorId" required>
					<option value="">{translate key="plugins.generic.editorAssignments.transfer.choose"}</option>
					{foreach from=$candidates key=userId item=name}
						<option value="{$userId|escape}">{$name|escape}</option>
					{/foreach}
				</select>
			{else}
				<p>{translate key="plugins.generic.editorAssignments.transfer.noCandidates"}</p>
			{/if}
		</div>
		{if $canNotify}
			<label class="erField erCheckbox"><input type="checkbox" name="notify" value="1" checked /> {translate key="plugins.generic.editorAssignments.transfer.notify"}</label>
		{/if}
		{if $editor}<p class="erHint">{translate key="plugins.generic.editorAssignments.transfer.hint"}</p>{/if}
		<div class="erButtons">
			<button type="submit" class="pkp_button pkp_button_primary" {if !$candidates}disabled{/if}>{if $editor}{translate key="plugins.generic.editorAssignments.transfer.submit"}{else}{translate key="plugins.generic.editorAssignments.assign.submit"}{/if}</button>
			<a href="{url page=$erPage anchor="editor-`$editor.id|default:0`"}">{translate key="common.cancel"}</a>
		</div>
	</form>
</div>
{/block}
