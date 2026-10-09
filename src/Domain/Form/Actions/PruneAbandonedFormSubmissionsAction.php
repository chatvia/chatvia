<?php

namespace Domain\Form\Actions;

use Domain\Form\Models\FormSubmission;

/**
 * Delete inline-form placeholders whose time limit has passed. A pending row
 * is created when an agent shows a form in chat; once it expires it can never
 * be claimed (SubmitFormAction rejects it) and holds no answers, so it is
 * dead weight. Submitted rows — the captured contacts — are never touched.
 */
class PruneAbandonedFormSubmissionsAction
{
    /**
     * @return int How many placeholders were deleted.
     */
    public function execute(): int
    {
        return FormSubmission::query()->abandoned()->delete();
    }
}
