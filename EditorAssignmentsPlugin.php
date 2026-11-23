<?php

/**
 * @file plugins/generic/editorAssignments/EditorAssignmentsPlugin.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class EditorAssignmentsPlugin
 *
 * @brief A backend page that shows which editor is responsible for which active
 *  submission, with actions to email an editor, transfer a submission to another
 *  editor and remove an assignment.
 */

namespace APP\plugins\generic\editorAssignments;

use APP\core\Application;
use APP\plugins\generic\editorAssignments\pages\EditorAssignmentsHandler;
use APP\template\TemplateManager;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;

class EditorAssignmentsPlugin extends GenericPlugin
{
    public function getName()
    {
        return 'EditorAssignmentsPlugin';
    }

    public function getDisplayName()
    {
        return __('plugins.generic.editorAssignments.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.editorAssignments.description');
    }

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            Hook::add('LoadHandler', $this->loadPageHandler(...));
            Hook::add('TemplateManager::setupBackendPage', $this->addBackendMenu(...));
        }
        return $success;
    }

    public function loadPageHandler(string $hookName, array $args): bool
    {
        if ($args[0] !== EditorAssignmentsHandler::PAGE) {
            return false;
        }
        $handler = &$args[3];
        $handler = new EditorAssignmentsHandler($this);
        return true;
    }

    /**
     * Left menu entry "Editor assignments" for journal managers, after the dashboards.
     */
    public function addBackendMenu(string $hookName, array $args): bool
    {
        $request = Application::get()->getRequest();
        $router = $request->getRouter();
        if (!$request->getContext() || !$request->getUser() || !$router || !$router->getHandler()) {
            return false;
        }
        $userRoles = (array) $router->getHandler()->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles)) {
            return false;
        }
        $templateMgr = TemplateManager::getManager($request);
        $menu = (array) $templateMgr->getState('menu');
        $entry = [
            'name' => __('plugins.generic.editorAssignments.title'),
            'url' => $router->url($request, null, EditorAssignmentsHandler::PAGE),
            'isCurrent' => $request->getRequestedPage() === EditorAssignmentsHandler::PAGE,
            'icon' => 'User',
        ];
        $pos = array_search('dashboards', array_keys($menu), true);
        $menu = $pos === false
            ? $menu + ['editorAssignments' => $entry]
            : array_slice($menu, 0, $pos + 1, true) + ['editorAssignments' => $entry] + array_slice($menu, $pos + 1, null, true);
        $templateMgr->setState(['menu' => $menu]);
        return false;
    }
}
