{**
 * plugins/generic/editorAssignments/templates/compose.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Email to the editor of a submission.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<h1 class="app__pageHeading">{$pageTitle|escape}</h1>
<div class="app__contentPanel erPanel" v-pre>
	<p><a href="{url page=$erPage anchor="editor-`$editor.id`"}">← {translate key="plugins.generic.editorAssignments.title"}</a></p>
	<form method="post" action="{url page=$erPage op="send"}" class="erForm">
		{csrf}
		<input type="hidden" name="submissionId" value="{$submissionId|escape}" />
		<input type="hidden" name="editorId" value="{$editor.id|escape}" />
		<div class="erField">
			<span class="erLabel">{translate key="email.to"}</span>
			{$editor.name|escape} &lt;{$editor.email|escape}&gt;
		</div>
		<div class="erField">
			<span class="erLabel">{translate key="submission.submission"}</span>
			#{$submissionId|escape} {$submissionTitle|escape}
		</div>
		<div class="erField">
			<label class="erLabel" for="erSubject">{translate key="email.subject"}</label>
			<input type="text" id="erSubject" name="subject" value="{$subject|escape}" required />
		</div>
		<div class="erField">
			<label class="erLabel" for="erBody">{translate key="email.body"}</label>
			<textarea id="erBody" name="body" rows="14" required>{$body|escape}</textarea>
		</div>
		<label class="erField erCheckbox"><input type="checkbox" name="copyToSender" value="1" /> {translate key="plugins.generic.editorAssignments.email.copyToSender"}</label>
		<div class="erButtons">
			<button type="submit" class="pkp_button pkp_button_primary">{translate key="plugins.generic.editorAssignments.email.send"}</button>
			<a href="{url page=$erPage anchor="editor-`$editor.id`"}">{translate key="common.cancel"}</a>
		</div>
	</form>
</div>
{/block}
