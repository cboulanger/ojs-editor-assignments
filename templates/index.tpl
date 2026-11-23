{**
 * plugins/generic/editorAssignments/templates/index.tpl
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * The editors and the active submissions they are assigned to.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
<h1 class="app__pageHeading">{$pageTitle|escape}</h1>
<div class="app__contentPanel erPanel" v-pre>
	{if $canFilter}
		<form method="get" action="{url page=$erPage}" class="erFilter" data-er-autosubmit>
			<input type="hidden" name="filter" value="1" />
			<label><input type="checkbox" name="showAll" value="1" {if $showAll}checked{/if} /> {translate key="plugins.generic.editorAssignments.showAll"}</label>
			<noscript><button type="submit" class="pkp_button">{translate key="common.apply"}</button></noscript>
		</form>
	{/if}
	<p class="erHint">{if $showAll}{translate key="plugins.generic.editorAssignments.intro" total=$total}{else}{translate key="plugins.generic.editorAssignments.introJournalEditors" total=$total}{/if}</p>
	{if !$rows && !$unassigned}
		<p>{translate key="plugins.generic.editorAssignments.empty"}</p>
	{else}
		<table class="erTable">
			<thead>
				<tr>
					<th class="erEditorCol">{translate key="plugins.generic.editorAssignments.editor"}</th>
					<th>{translate key="plugins.generic.editorAssignments.submissions"}</th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$rows item=row}
					<tr id="editor-{$row.id|escape}">
						<td class="erEditorCol">
							<strong>{$row.name|escape}</strong>
							<div class="erCount">{translate key="plugins.generic.editorAssignments.count" number=$row.count}</div>
						</td>
						<td>
							{if $row.submissions}
								{include file=$erSubmissionsTable submissions=$row.submissions editorId=$row.id}
							{else}
								<span class="erHint">–</span>
							{/if}
						</td>
					</tr>
				{/foreach}
				{if $unassigned}
					<tr id="editor-0" class="erUnassigned">
						<td class="erEditorCol">
							<strong>{if $showAll}{translate key="plugins.generic.editorAssignments.noEditor"}{else}{translate key="plugins.generic.editorAssignments.noJournalEditor"}{/if}</strong>
							<div class="erCount">{translate key="plugins.generic.editorAssignments.count" number=$unassignedCount}</div>
						</td>
						<td>{include file=$erSubmissionsTable submissions=$unassigned editorId=0}</td>
					</tr>
				{/if}
			</tbody>
		</table>
	{/if}
</div>
{/block}
