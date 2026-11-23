<?php

/**
 * @file plugins/generic/editorAssignments/classes/Assignments.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Assignments
 *
 * @brief Reading and changing the editor assignments of submissions. An editor is a
 *  user assigned to a submission in a user group with the role Journal manager or
 *  Section editor (the "Editors" of a submission's participants list). Changes are
 *  made and logged the way OJS's participants list does it.
 */

namespace APP\plugins\generic\editorAssignments\classes;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use PKP\core\Core;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\notification\Notification;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class Assignments
{
    const EDITOR_ROLES = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

    /** Locale key of the name of OJS's default user group "Journal editor" */
    const JOURNAL_EDITOR_KEY = 'default.groups.name.editor';

    /**
     * Submissions in the editorial workflow: completed, neither published nor declined.
     * @return Submission[] by id
     */
    public static function activeSubmissions(int $contextId): array
    {
        $submissions = [];
        $collector = Repo::submission()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByStatus([Submission::STATUS_QUEUED, Submission::STATUS_SCHEDULED]);
        foreach ($collector->getMany() as $submission) {
            if ((string) $submission->getData('submissionProgress') === '') {
                $submissions[$submission->getId()] = $submission;
            }
        }
        ksort($submissions);
        return $submissions;
    }

    /**
     * The editors of the given submissions.
     * @return array userId => [submissionId => true]
     */
    public static function editorsBySubmission(array $submissionIds): array
    {
        $editors = [];
        if (!$submissionIds) {
            return $editors;
        }
        foreach (array_chunk($submissionIds, 500) as $chunk) {
            $assignments = StageAssignment::withSubmissionIds($chunk)->withRoleIds(self::EDITOR_ROLES)->get();
            foreach ($assignments as $assignment) {
                $editors[(int) $assignment->userId][(int) $assignment->submissionId] = true;
            }
        }
        return $editors;
    }

    /**
     * The journal's "Journal editor" user groups: OJS's default group of that name
     * (identified by its locale key, so renaming it doesn't matter), or a Journal manager
     * group named like it in one of its locales.
     * @return int[]
     */
    public static function journalEditorGroupIds(int $contextId): array
    {
        $ids = [];
        foreach (UserGroup::withContextIds([$contextId])->withRoleIds([Role::ROLE_ID_MANAGER])->get() as $group) {
            $matches = ($group->nameLocaleKey ?? null) === self::JOURNAL_EDITOR_KEY;
            foreach ((array) $group->name as $locale => $name) {
                $matches = $matches || ($name !== '' && $name === __(self::JOURNAL_EDITOR_KEY, [], $locale));
            }
            if ($matches) {
                $ids[] = (int) $group->getKey();
            }
        }
        return $ids;
    }

    /**
     * Users who can be assigned as editors: active users with the role Journal manager
     * or Section editor in the journal; with $userGroupIds only members of these groups.
     * @param int[] $roleIds
     * @param ?int[] $userGroupIds
     * @return array userId => full name, sorted by name
     */
    public static function candidates(int $contextId, array $roleIds = self::EDITOR_ROLES, ?array $userGroupIds = null): array
    {
        $users = [];
        if ($userGroupIds === []) {
            return $users;
        }
        $collector = Repo::user()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByRoleIds($roleIds)
            ->filterByUserGroupIds($userGroupIds);
        foreach ($collector->getMany() as $user) {
            $users[$user->getId()] = $user->getFullName();
        }
        asort($users, SORT_NATURAL | SORT_FLAG_CASE);
        return $users;
    }

    public static function isEditor(int $submissionId, int $userId): bool
    {
        return StageAssignment::withSubmissionIds([$submissionId])->withUserId($userId)->withRoleIds(self::EDITOR_ROLES)->exists();
    }

    /**
     * Assign a user as editor of a submission. The user group is the first of the preferred
     * ones the user is a member of, else their Section editor group, else their Journal manager group.
     * @param int[] $preferredGroupIds
     * @return bool false if the user has no editor role in the journal
     */
    public static function assign(Submission $submission, User $editor, array $preferredGroupIds = []): bool
    {
        $contextId = (int) $submission->getData('contextId');
        $groups = UserGroup::withContextIds([$contextId])
            ->withRoleIds(self::EDITOR_ROLES)
            ->whereHas('userUserGroups', fn ($query) => $query->withUserId($editor->getId())->withActive())
            ->get();
        if ($groups->isEmpty()) {
            return false;
        }
        $group = null;
        foreach ($preferredGroupIds as $groupId) {
            $group ??= $groups->first(fn ($g) => (int) $g->getKey() === (int) $groupId);
        }
        $group ??= $groups->first(fn ($g) => (int) $g->roleId === Role::ROLE_ID_SUB_EDITOR)
            ?? $groups->first();
        $existing = StageAssignment::withSubmissionIds([$submission->getId()])->withUserId($editor->getId())
            ->withUserGroupId((int) $group->getKey())->exists();
        if (!$existing) {
            Repo::stageAssignment()->build($submission->getId(), (int) $group->getKey(), $editor->getId(), (bool) $group->recommendOnly);
            self::log($submission, PKPSubmissionEventLogEntry::SUBMISSION_LOG_ADD_PARTICIPANT, 'submission.event.participantAdded',
                $editor, $group);
        }
        self::updateNotifications($submission);
        return true;
    }

    /**
     * Remove a user's editor assignments (in all editorial user groups) from a submission.
     * Other assignments of the user (e.g. as author or reviewer) stay.
     * @return int Number of removed assignments
     */
    public static function unassign(Submission $submission, int $userId): int
    {
        $assignments = StageAssignment::withSubmissionIds([$submission->getId()])->withUserId($userId)
            ->withRoleIds(self::EDITOR_ROLES)->get();
        $editor = Repo::user()->get($userId, true);
        foreach ($assignments as $assignment) {
            $group = Repo::userGroup()->get($assignment->userGroupId);
            $assignment->delete();
            if ($editor && $group) {
                self::log($submission, PKPSubmissionEventLogEntry::SUBMISSION_LOG_REMOVE_PARTICIPANT, 'submission.event.participantRemoved',
                    $editor, $group);
            }
        }
        if ($assignments->isNotEmpty()) {
            self::updateNotifications($submission);
        }
        return $assignments->count();
    }

    /**
     * The id of the user group in which a user is assigned as editor (to keep it when transferring).
     */
    public static function editorGroupId(int $submissionId, int $userId): ?int
    {
        $assignment = StageAssignment::withSubmissionIds([$submissionId])->withUserId($userId)
            ->withRoleIds(self::EDITOR_ROLES)->first();
        return $assignment ? (int) $assignment->userGroupId : null;
    }

    /**
     * Same entries in the submission's activity log as OJS's participants list.
     */
    protected static function log(Submission $submission, int $eventType, string $message, User $editor, $group): void
    {
        $request = Application::get()->getRequest();
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => $eventType,
            'userId' => Validation::loggedInAs() ?? $request->getUser()?->getId(),
            'message' => $message,
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'userFullName' => $editor->getFullName(),
            'username' => $editor->getUsername(),
            'userGroupName' => $group->name,
        ]);
        Repo::eventLog()->add($eventLog);
    }

    /**
     * Update the "assign an editor" tasks of the submission, as OJS does after changing participants.
     */
    protected static function updateNotifications(Submission $submission): void
    {
        $request = Application::get()->getRequest();
        $notificationMgr = new NotificationManager();
        $notificationMgr->updateNotification($request, $notificationMgr->getDecisionStageNotifications(), null,
            Application::ASSOC_TYPE_SUBMISSION, $submission->getId());
        if (StageAssignment::withSubmissionIds([$submission->getId()])->withRoleIds(self::EDITOR_ROLES)->exists()) {
            Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submission->getId())
                ->withType(Notification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED)
                ->delete();
        }
    }
}
