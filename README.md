# Editor Assignments for OJS 3.5

A lightweight OJS plugin that shows journal managers **which editor is responsible for which submission**, and lets them change it.

The specification is in [issue #1](https://github.com/cboulanger/ojs-editor-assignments/issues/1). The plugin follows the patterns of [Dossier Manager](https://github.com/cboulanger/ojs-dossier-manager) and [Book Review Manager](https://github.com/cboulanger/ojs-book-review-manager).

## What it does

A left menu entry **Editor assignments** (journal managers and site admins) opens a table:

| Editor | Submissions |
| --- | --- |
| Name, number of submissions | ID · Authors · Title (links to the workflow) · *Email editor* · *Transfer to other editor* · *Remove assignment* |

- **Rows**, by default: the members of the user group **Journal editor** (OJS's default group, recognised even if renamed). A last row lists the submissions **without a journal editor**, with the names of their other editors and a link *Assign editor*.
- **Show all assignments** (checkbox at the top, remembered for the session): every user with the role Section editor (e.g. section, guest and book review editors), and everybody else who is assigned as editor of an active submission (for example journal managers or production editors). The last row then lists the submissions **without any editor**.
- If the journal has no *Journal editor* group, the page always shows all assignments.
- **Active submissions**: submitted, and neither published nor declined (status queued or scheduled).
- **Editor** of a submission: a participant in a user group with the role Journal manager or Section editor, as in the *Editors* section of OJS's participants list.
- **Email editor**: a form with a prefilled message (submission, authors, link to the workflow), sent from the manager's address, optionally with a copy to the manager. It is recorded in the submission's email log.
- **Transfer to other editor**: choose a journal editor (with *Show all assignments*: any journal manager or section editor) who is not yet assigned. The new editor is assigned in the same user group as the previous one if they belong to it, otherwise (unless *Show all assignments* is checked) as journal editor, otherwise in their section editor group, otherwise in their journal manager group. The previous editor is then removed. Optionally the new editor gets OJS's email *Editor assigned* (`EDITOR_ASSIGN`, editable under *Settings › Emails*).
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
| `dev/package.sh`, `dev/gallery/` | Release package and Plugin Gallery entry |

## Releases

The release package contains only what OJS needs (a rule of the PKP Plugin Gallery); `dev/` and the repository files are marked `export-ignore` in [`.gitattributes`](.gitattributes).

1. Set `<release>` (four numbers) and `<date>` in `version.xml` and commit.
2. Optional: check the package locally with `dev/package.sh` (writes `dist/`).
3. Push an annotated tag matching the version (`0.1.0.0` → `v0.1.0`, `0.1.0.2` → `v0.1.0-2`). Its first line becomes the release description in the gallery:

   ```sh
   git tag -a v0.1.0 -m "What changed, in one line" && git push origin v0.1.0
   ```

4. The [Release workflow](.github/workflows/release.yml) runs the [CI](.github/workflows/ci.yml) checks and creates a **draft** release with `editorAssignments-v0.1.0.tar.gz`, its `.md5`, `gallery-release.xml` and `gallery-plugin.xml`. Test the package, then publish the draft.
5. Plugin Gallery: open a pull request on [pkp/plugin-gallery](https://github.com/pkp/plugin-gallery) that adds `gallery-release.xml` to the plugin's entry in `plugins.xml` (the first time: `gallery-plugin.xml`). The gallery texts are in [`dev/gallery/`](dev/gallery/).

## License

GNU General Public License v3. See [LICENSE](LICENSE).
