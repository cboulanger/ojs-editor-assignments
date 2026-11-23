{**
 * plugins/generic/editorAssignments/templates/submissions.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * The nested table of an editor's submissions ($editorId 0: submissions without editor).
 *}
<table class="erSubTable">
	<thead>
		<tr>
			<th class="erIdCol">{translate key="common.id"}</th>
			<th class="erAuthorsCol">{translate key="submission.authors"}</th>
			<th>{translate key="common.title"}</th>
			<th class="erActionsCol"><span class="-screenReader">{translate key="plugins.generic.editorAssignments.actions"}</span></th>
		</tr>
	</thead>
	<tbody>
		{foreach from=$submissions item=submission}
			<tr>
				<td class="erIdCol">{$submission.id|escape}</td>
				<td class="erAuthorsCol">{$submission.authors|escape}</td>
				<td>
					{if $submission.url}<a href="{$submission.url|escape}">{$submission.title|strip_unsafe_html}</a>{else}{$submission.title|strip_unsafe_html}{/if}
					{if $submission.stage}<div class="erStage">{$submission.stage|escape}</div>{/if}
					{if $submission.otherEditors}<div class="erStage">{translate key="plugins.generic.editorAssignments.otherEditors" names=$submission.otherEditors|escape}</div>{/if}
				</td>
				<td class="erActionsCol">
					{if $editorId}
						<a href="{url page=$erPage op="compose" params=["submissionId" => $submission.id, "editorId" => $editorId]}">{translate key="plugins.generic.editorAssignments.action.email"}</a>
						<a href="{url page=$erPage op="transfer" params=["submissionId" => $submission.id, "editorId" => $editorId]}">{translate key="plugins.generic.editorAssignments.action.transfer"}</a>
						<form method="post" action="{url page=$erPage op="remove"}" class="erInline"
							data-er-confirm="{translate key="plugins.generic.editorAssignments.remove.confirm" submissionId=$submission.id}">
							{csrf}
							<input type="hidden" name="submissionId" value="{$submission.id|escape}" />
							<input type="hidden" name="editorId" value="{$editorId|escape}" />
							<button type="submit" class="erLinkButton erDanger">{translate key="plugins.generic.editorAssignments.action.remove"}</button>
						</form>
					{else}
						<a href="{url page=$erPage op="transfer" params=["submissionId" => $submission.id, "editorId" => 0]}">{translate key="plugins.generic.editorAssignments.action.assign"}</a>
					{/if}
				</td>
			</tr>
		{/foreach}
	</tbody>
</table>
