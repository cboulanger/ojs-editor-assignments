<?php

/**
 * @file plugins/generic/editorAssignments/pages/EditorAssignmentsHandler.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class EditorAssignmentsHandler
 *
 * @brief Backend pages (left menu "Editor assignments"): the table of editors and
 *  their submissions, the email form, the transfer form, and removing an assignment.
 *  GET shows, POST changes and redirects.
 */

namespace APP\plugins\generic\editorAssignments\pages;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\plugins\generic\editorAssignments\classes\Assignments;
use APP\plugins\generic\editorAssignments\mailables\EditorMessage;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\mailables\EditorAssigned;
use PKP\notification\Notification;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use PKP\user\User;

class EditorAssignmentsHandler extends Handler
{
    const PAGE = 'editorAssignments';

    const OPS = ['index', 'compose', 'send', 'transfer', 'saveTransfer', 'remove'];

    /** Session key: "Show all assignments" is checked */
    const SESSION_SHOW_ALL = 'editorAssignments.showAll';

    public $_isBackendPage = true;

    /** @var \APP\plugins\generic\editorAssignments\EditorAssignmentsPlugin */
    public $plugin;

    public function __construct($plugin)
    {
        parent::__construct();
        $this->plugin = $plugin;
        $this->addRoleAssignment([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], self::OPS);
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /* ---------- Overview ---------- */

    /**
     * One row per editor with the submissions they are assigned to, followed by the
     * submissions without editor. Listed are
     *  - by default: the members of the group "Journal editor"; the last row has the
     *    submissions without a journal editor (with the names of their other editors);
     *  - with "Show all assignments": the users with the role Section editor and everybody
     *    else assigned as editor (e.g. journal managers); the last row has the submissions
     *    without any editor.
     * The checkbox is remembered for the session, so that the actions return to the same view.
     */
    public function index($args, $request)
    {
        $context = $request->getContext();
        if ($request->getUserVar('filter') !== null) {
            $request->getSession()->put(self::SESSION_SHOW_ALL, (bool) $request->getUserVar('showAll'));
        }
        $journalEditorGroupIds = Assignments::journalEditorGroupIds($context->getId());
        $showAll = $this->showAll($request) || !$journalEditorGroupIds;

        $submissions = Assignments::activeSubmissions($context->getId());
        $editorsBySubmission = Assignments::editorsBySubmission(array_keys($submissions));

        if ($showAll) {
            $names = Assignments::candidates($context->getId(), [Role::ROLE_ID_SUB_EDITOR]);
            foreach (array_keys($editorsBySubmission) as $userId) {
                if (!isset($names[$userId]) && ($user = Repo::user()->get($userId, true))) {
                    $names[$userId] = $user->getFullName();
                }
            }
            asort($names, SORT_NATURAL | SORT_FLAG_CASE);
        } else {
            $names = Assignments::candidates($context->getId(), Assignments::EDITOR_ROLES, $journalEditorGroupIds);
        }

        $rows = [];
        $assigned = [];
        foreach ($names as $userId => $name) {
            $items = [];
            foreach (array_keys($editorsBySubmission[$userId] ?? []) as $submissionId) {
                $items[] = $this->submissionRow($submissions[$submissionId]);
                $assigned[$submissionId] = true;
            }
            $rows[] = ['id' => $userId, 'name' => $name, 'submissions' => $items, 'count' => count($items)];
        }

        // Without (journal) editor; when filtered, with the names of the submission's other editors
        $otherEditors = [];
        if (!$showAll) {
            foreach ($editorsBySubmission as $userId => $submissionIds) {
                foreach (array_keys($submissionIds) as $submissionId) {
                    if (!isset($assigned[$submissionId]) && ($user = Repo::user()->get($userId, true))) {
                        $otherEditors[$submissionId][] = $user->getFullName();
                    }
                }
            }
        }
        $unassigned = [];
        foreach ($submissions as $submissionId => $submission) {
            if (!isset($assigned[$submissionId])) {
                $unassigned[] = $this->submissionRow($submission, $otherEditors[$submissionId] ?? []);
            }
        }
        return $this->display($request, 'index.tpl', [
            'rows' => $rows,
            'unassigned' => $unassigned,
            'unassignedCount' => count($unassigned),
            'total' => count($submissions),
            'showAll' => $showAll,
            'canFilter' => (bool) $journalEditorGroupIds,
        ]);
    }

    protected function showAll($request): bool
    {
        return (bool) $request->getSession()->get(self::SESSION_SHOW_ALL, false);
    }

    /**
     * @param string[] $otherEditors Names of the submission's editors not listed on the page
     */
    protected function submissionRow(Submission $submission, array $otherEditors = []): array
    {
        $publication = $submission->getCurrentPublication();
        return [
            'id' => $submission->getId(),
            'authors' => $publication?->getShortAuthorString() ?? '',
            'title' => $publication?->getLocalizedFullTitle(null, 'html') ?? '',
            'stage' => $this->stageName((int) $submission->getData('stageId')),
            'url' => Repo::submission()->getWorkflowUrlByUserRoles($submission),
            'otherEditors' => implode(', ', $otherEditors),
        ];
    }

    /**
     * Possible new editors in the transfer form: like the overview, only journal editors
     * unless "Show all assignments" is checked.
     * @return array userId => name
     */
    protected function transferCandidates($request): array
    {
        $contextId = $request->getContext()->getId();
        $groupIds = Assignments::journalEditorGroupIds($contextId);
        return $this->showAll($request) || !$groupIds
            ? Assignments::candidates($contextId)
            : Assignments::candidates($contextId, Assignments::EDITOR_ROLES, $groupIds);
    }

    /* ---------- Email editor ---------- */

    public function compose($args, $request)
    {
        [$submission, $editor] = $this->requireAssignment($request, false);
        $user = $request->getUser();
        $publication = $submission->getCurrentPublication();
        $title = strip_tags((string) $publication?->getLocalizedFullTitle());
        $authors = (string) $publication?->getShortAuthorString();
        $body = __('plugins.generic.editorAssignments.email.defaultBody', [
            'editorName' => $editor->getFullName(),
            'submissionId' => $submission->getId(),
            'title' => $title,
            'authors' => $authors,
            'url' => Repo::submission()->getWorkflowUrlByUserRoles($submission, $editor->getId()),
            'senderName' => $user->getFullName(),
        ]);
        return $this->display($request, 'compose.tpl', [
            'submissionId' => $submission->getId(),
            'submissionTitle' => $title,
            'editor' => ['id' => $editor->getId(), 'name' => $editor->getFullName(), 'email' => $editor->getEmail()],
            'subject' => __('plugins.generic.editorAssignments.email.defaultSubject',
                ['submissionId' => $submission->getId(), 'title' => $title]),
            'body' => $body,
        ], 'plugins.generic.editorAssignments.email.title');
    }

    public function send($args, $request)
    {
        $this->requirePost($request);
        [$submission, $editor] = $this->requireAssignment($request, false);
        $user = $request->getUser();
        $subject = trim((string) $request->getUserVar('subject'));
        $text = trim((string) $request->getUserVar('body'));
        if ($subject === '' || $text === '') {
            $this->flash($request, __('plugins.generic.editorAssignments.email.empty'), Notification::NOTIFICATION_TYPE_ERROR);
            $request->redirect(null, self::PAGE, 'compose', null,
                ['submissionId' => $submission->getId(), 'editorId' => $editor->getId()]);
        }
        $mailable = new EditorMessage($request->getContext(), $submission);
        $mailable->sender($user)
            ->recipients([$editor])
            ->subject($subject)
            ->body(self::textToHtml($text));
        if ($request->getUserVar('copyToSender')) {
            $mailable->cc($user->getEmail(), $user->getFullName());
        }
        Mail::send($mailable);
        // There is no event type for a message to an editor; the email log shows subject and recipients
        Repo::emailLogEntry()->logMailable(SubmissionEmailLogEventType::EDITOR_ASSIGN, $mailable, $submission, $user);
        $this->flash($request, __('plugins.generic.editorAssignments.email.sent', ['name' => $editor->getFullName()]));
        $request->redirect(null, self::PAGE, null, null, null, 'editor-' . $editor->getId());
    }

    /* ---------- Transfer / assign ---------- */

    /**
     * Form to transfer a submission to another editor (editorId given) or to assign an
     * editor to a submission without one (editorId=0).
     */
    public function transfer($args, $request)
    {
        [$submission, $editor] = $this->requireAssignment($request, true);
        $candidates = $this->transferCandidates($request);
        foreach (array_keys($candidates) as $userId) {
            if (Assignments::isEditor($submission->getId(), $userId)) {
                unset($candidates[$userId]);
            }
        }
        $publication = $submission->getCurrentPublication();
        return $this->display($request, 'transfer.tpl', [
            'submissionId' => $submission->getId(),
            'submissionTitle' => strip_tags((string) $publication?->getLocalizedFullTitle()),
            'authors' => (string) $publication?->getShortAuthorString(),
            'editor' => $editor ? ['id' => $editor->getId(), 'name' => $editor->getFullName()] : null,
            'candidates' => $candidates,
            'canNotify' => (bool) Repo::emailTemplate()->getByKey($request->getContext()->getId(), EditorAssigned::getEmailTemplateKey()),
        ], $editor ? 'plugins.generic.editorAssignments.transfer.title' : 'plugins.generic.editorAssignments.assign.title');
    }

    public function saveTransfer($args, $request)
    {
        $this->requirePost($request);
        [$submission, $editor] = $this->requireAssignment($request, true);
        $context = $request->getContext();
        $newEditorId = (int) $request->getUserVar('newEditorId');
        $candidates = $this->transferCandidates($request);
        $newEditor = isset($candidates[$newEditorId]) ? Repo::user()->get($newEditorId) : null;
        if (!$newEditor || $newEditorId === $editor?->getId()) {
            $this->flash($request, __('plugins.generic.editorAssignments.transfer.chooseEditor'), Notification::NOTIFICATION_TYPE_ERROR);
            $request->redirect(null, self::PAGE, 'transfer', null,
                ['submissionId' => $submission->getId(), 'editorId' => $editor?->getId() ?? 0]);
        }
        // Keep the previous editor's group; from the journal editors' view, prefer "Journal editor"
        $groupIds = array_filter([$editor ? Assignments::editorGroupId($submission->getId(), $editor->getId()) : null]);
        if (!$this->showAll($request)) {
            $groupIds = array_merge($groupIds, Assignments::journalEditorGroupIds($context->getId()));
        }
        if (!Assignments::assign($submission, $newEditor, $groupIds)) {
            $this->flash($request, __('plugins.generic.editorAssignments.transfer.chooseEditor'), Notification::NOTIFICATION_TYPE_ERROR);
            $request->redirect(null, self::PAGE);
        }
        if ($editor) {
            Assignments::unassign($submission, $editor->getId());
        }
        if ($request->getUserVar('notify')) {
            $this->notifyAssigned($context, $submission, $newEditor);
        }
        $this->flash($request, $editor
            ? __('plugins.generic.editorAssignments.transfer.done', ['submissionId' => $submission->getId(), 'from' => $editor->getFullName(), 'to' => $newEditor->getFullName()])
            : __('plugins.generic.editorAssignments.assign.done', ['submissionId' => $submission->getId(), 'name' => $newEditor->getFullName()]));
        $request->redirect(null, self::PAGE, null, null, null, 'editor-' . $newEditor->getId());
    }

    /**
     * OJS's email "Editor assigned" (Settings › Emails), sent from the journal contact, as when OJS assigns editors itself.
     */
    protected function notifyAssigned($context, Submission $submission, User $editor): void
    {
        $template = Repo::emailTemplate()->getByKey($context->getId(), EditorAssigned::getEmailTemplateKey());
        if (!$template) {
            return;
        }
        $mailable = new EditorAssigned($context, Repo::submission()->get($submission->getId()));
        $mailable->from($context->getData('contactEmail'), $context->getData('contactName'))
            ->recipients([$editor])
            ->subject($template->getLocalizedData('subject') ?? '')
            ->body($template->getLocalizedData('body') ?? '');
        Mail::send($mailable);
        Repo::emailLogEntry()->logMailable(SubmissionEmailLogEventType::EDITOR_ASSIGN, $mailable, $submission);
    }

    /* ---------- Remove ---------- */

    public function remove($args, $request)
    {
        $this->requirePost($request);
        [$submission, $editor] = $this->requireAssignment($request, false);
        Assignments::unassign($submission, $editor->getId());
        $message = __('plugins.generic.editorAssignments.remove.done',
            ['submissionId' => $submission->getId(), 'name' => $editor->getFullName()]);
        $noEditor = !Assignments::editorsBySubmission([$submission->getId()]);
        $this->flash($request, $noEditor ? $message . ' ' . __('plugins.generic.editorAssignments.remove.noEditorLeft') : $message,
            $noEditor ? Notification::NOTIFICATION_TYPE_WARNING : Notification::NOTIFICATION_TYPE_SUCCESS);
        $request->redirect(null, self::PAGE, null, null, null, 'editor-' . $editor->getId());
    }

    /* ---------- Helpers ---------- */

    protected function display($request, string $template, array $vars, string $titleKey = 'plugins.generic.editorAssignments.title')
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $base = $request->getBaseUrl() . '/' . $this->plugin->getPluginPath();
        $templateMgr->addStyleSheet('editorAssignments', $base . '/css/editorAssignments.css', ['contexts' => 'backend']);
        $templateMgr->addJavaScript('editorAssignments', $base . '/js/editorAssignments.js', ['contexts' => 'backend']);
        $templateMgr->assign($vars + [
            'pageTitle' => __($titleKey),
            'erPage' => self::PAGE,
            'erSubmissionsTable' => $this->plugin->getTemplateResource('submissions.tpl'),
        ]);
        return $templateMgr->display($this->plugin->getTemplateResource($template));
    }

    protected function requirePost($request): void
    {
        if (!$request->isPost() || !$request->checkCSRF()) {
            $request->redirect(null, self::PAGE);
        }
    }

    /**
     * The active submission (submissionId) of the journal and its editor (editorId).
     * @param bool $editorOptional editorId=0 is allowed (assigning an editor)
     * @return array [Submission, ?User]
     */
    protected function requireAssignment($request, bool $editorOptional): array
    {
        $submissionId = (int) $request->getUserVar('submissionId');
        $editorId = (int) $request->getUserVar('editorId');
        $submissions = $submissionId ? Assignments::activeSubmissions($request->getContext()->getId()) : [];
        $submission = $submissions[$submissionId] ?? null;
        $editor = $editorId && $submission && Assignments::isEditor($submissionId, $editorId) ? Repo::user()->get($editorId, true) : null;
        if (!$submission || (!$editor && ($editorId || !$editorOptional))) {
            $this->flash($request, __('plugins.generic.editorAssignments.notFound'), Notification::NOTIFICATION_TYPE_ERROR);
            $request->redirect(null, self::PAGE);
        }
        return [$submission, $editor];
    }

    protected function flash($request, string $message, int $type = Notification::NOTIFICATION_TYPE_SUCCESS): void
    {
        (new NotificationManager())->createTrivialNotification($request->getUser()->getId(), $type, ['contents' => $message]);
    }

    /**
     * Plain text from the form: paragraphs, line breaks and clickable links.
     */
    protected static function textToHtml(string $text): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text));
        return implode('', array_map(function ($p) {
            $html = nl2br(htmlspecialchars(trim($p), ENT_QUOTES));
            $html = preg_replace('~https?://[^\s<]+~', '<a href="$0">$0</a>', $html);
            return '<p>' . $html . '</p>';
        }, $paragraphs));
    }

    protected function stageName(int $stageId): string
    {
        return match ($stageId) {
            WORKFLOW_STAGE_ID_SUBMISSION => __('manager.publication.submissionStage'),
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW => __('manager.publication.reviewStage'),
            WORKFLOW_STAGE_ID_EDITING => __('submission.copyediting'),
            WORKFLOW_STAGE_ID_PRODUCTION => __('manager.publication.productionStage'),
            default => '',
        };
    }
}
