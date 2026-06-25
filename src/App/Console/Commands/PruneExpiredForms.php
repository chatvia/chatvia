<?php

namespace App\Console\Commands;

use Domain\Form\Models\FormSubmission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Delete abandoned inline-form placeholders whose TTL has lapsed. A pending form_submissions row is created when an agent renders a form in chat; once past its expires_at it can never be claimed (SubmitFormAction rejects it) and carries no captured answers, so it is dead weight. Submitted rows — the actual captured contacts — are never touched.')]
#[Signature('app:prune-expired-forms')]
class PruneExpiredForms extends Command
{
    public function handle(): int
    {
        $deleted = FormSubmission::query()
            ->where('status', FormSubmission::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Pruned {$deleted} expired form submission(s).");

        return self::SUCCESS;
    }
}
