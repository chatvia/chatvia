<?php

namespace Domain\Form\Actions;

use Domain\Form\Models\FormSubmission;

/**
 * Delete inline-form placeholders whose time limit has passed. A pending row
 * is created when an agent shows a form in chat; once it expires it can never
 * be claimed (SubmitFormAction rejects it) and holds no answers, so it is
 * dead weight. Submitted rows — the captured contacts — are never touched.
 *
 * Rows go in batches, found through form_submissions_pending_expiry_idx
 * (`expires_at` of pending rows).
 */
class PruneAbandonedFormSubmissionsAction
{
    /**
     * @return int How many placeholders were deleted.
     */
    public function execute(int $batchSize = 5000): int
    {
        $deleted = 0;

        do {
            $batch = FormSubmission::query()
                ->whereIn('id', FormSubmission::query()->abandoned()->select('id')->limit($batchSize))
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
