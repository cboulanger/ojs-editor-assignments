<?php

/**
 * @file plugins/generic/editorAssignments/mailables/EditorMessage.php
 *
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class EditorMessage
 *
 * @brief A message from a journal manager to the editor of a submission,
 *  written on the "Email editor" page. Not a template: the text is written each time.
 */

namespace APP\plugins\generic\editorAssignments\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;

class EditorMessage extends Mailable
{
    use Recipient;
    use Sender;

    protected static array $groupIds = [self::GROUP_SUBMISSION];
    protected static array $fromRoleIds = [Role::ROLE_ID_MANAGER];
    protected static array $toRoleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
    }
}
