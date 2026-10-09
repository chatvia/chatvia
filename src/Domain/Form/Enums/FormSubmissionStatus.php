<?php

declare(strict_types=1);

namespace Domain\Form\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Lifecycle of a `form_submissions` row:
 *
 *   - Pending   → an agent rendered the form; the visitor has not sent it yet.
 *   - Submitted → the visitor sent it (or the conversation captured the
 *                 details directly); this is a captured contact.
 *   - Expired   → the pending form outlived its time limit and can no longer
 *                 be sent.
 *
 * The values are stored in the database and sent to the chat UI inside the
 * form artifact, so they must not change.
 */
#[TypeScript]
enum FormSubmissionStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Expired = 'expired';
}
