# Editor Assignments for OJS 3.5

A lightweight OJS plugin that shows journal managers **which editor is responsible for which submission**, and lets them change it.

The specification is in [issue #1](https://github.com/cboulanger/ojs-editor-assignments/issues/1). The plugin follows the patterns of [Dossier Manager](https://github.com/cboulanger/ojs-dossier-manager) and [Book Review Manager](https://github.com/cboulanger/ojs-book-review-manager).

## What it does

A left menu entry **Editor assignments** (journal managers and site admins) opens a table:

| Editor | Submissions |
| --- | --- |
| Name, number of submissions | ID · Authors · Title (links to the workflow) · *Email editor* · *Transfer to other editor* · *Remove assignment* |

- **Rows**: every user with the role Section editor, and everybody else who is assigned as editor of an active submission (for example journal managers). A last row lists the submissions **without editor**, with a link *Assign editor*.
- **Active submissions**: submitted, and neither published nor declined (status queued or scheduled).
- **Editor** of a submission: a participant in a user group with the role Journal manager or Section editor, as in the *Editors* section of OJS's participants list.
- **Email editor**: a form with a prefilled message (submission, authors, link to the workflow), sent from the manager's address, optionally with a copy to the manager. It is recorded in the submission's email log.
- **Transfer to other editor**: choose a journal manager or section editor who is not yet assigned. The new editor is assigned in the same user group as the previous one if they belong to it, otherwise in their section editor group, otherwise in their journal manager group. The previous editor is then removed. Optionally the new editor gets OJS's email *Editor assigned* (`EDITOR_ASSIGN`, editable under *Settings › Emails*).
- **Remove assignment** (with confirmation): removes the editor's editorial assignments from the submission; other roles (author, reviewer) stay. A warning appears if the submission has no editor left.
- Changes are written to the submission's activity log and update OJS's "assign an editor" tasks, as when using the participants list.

English and German.

## Installation

Requirements: OJS 3.5, PHP 8.2 or later.

```sh
cd /path/to/ojs/plugins/generic
git clone https://github.com/cboulanger/ojs-editor-assignments.git editorAssignments   # the folder name matters
cd /path/to/ojs
php lib/pkp/tools/installPluginVersion.php plugins/generic/editorAssignments/version.xml
```

Then enable *Editor Assignments* under *Website Settings › Plugins › Generic Plugins*.

## Files

| Path | Contents |
| --- | --- |
| `EditorAssignmentsPlugin.php` | Hooks: page handler, left menu entry |
| `pages/EditorAssignmentsHandler.php` | The page and its actions (GET shows, POST changes and redirects) |
| `classes/Assignments.php` | Reading and changing editor assignments, activity log, notifications |
| `mailables/EditorMessage.php` | The email to an editor |
| `templates/` | Overview, nested submissions table, email form, transfer form |

## License

GNU General Public License v3. See [LICENSE](LICENSE).
