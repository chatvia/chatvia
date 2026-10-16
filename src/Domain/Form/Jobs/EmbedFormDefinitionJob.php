<?php

declare(strict_types=1);

namespace Domain\Form\Jobs;

use Domain\Agent\Models\Agent;
use Domain\Form\Actions\EmbedFormDefinitionAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Embeds one form definition off the request path, so the find-forms tool can
 * match it to what a visitor asks. Its token cost is booked to the workspace
 * that owns the form's agent.
 */
class EmbedFormDefinitionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $formId) {}

    public function handle(EmbedFormDefinitionAction $action): void
    {
        $action->execute($this->formId);
    }
}
