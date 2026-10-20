<?php

declare(strict_types=1);

namespace App\Console\Commands\Prune;

use Domain\Form\Actions\PruneAbandonedFormSubmissionsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled clean-up of inline forms that were shown but never sent
 * ({@see PruneAbandonedFormSubmissionsAction}).
 */
#[Description('Delete abandoned inline-form placeholders whose TTL has lapsed. A pending form_submissions row is created when an agent renders a form in chat; once past its expires_at it can never be claimed (SubmitFormAction rejects it) and carries no captured answers, so it is dead weight. Submitted rows — the actual captured contacts — are never touched.')]
#[Signature('chatvia:prune:forms')]
class PruneExpiredForms extends Command
{
    public function handle(PruneAbandonedFormSubmissionsAction $prune): int
    {
        $deleted = $prune->execute();

        $this->info("Pruned {$deleted} expired form submission(s).");

        return self::SUCCESS;
    }
}
