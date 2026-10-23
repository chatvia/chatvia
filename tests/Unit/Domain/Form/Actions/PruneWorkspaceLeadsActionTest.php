<?php

use Domain\Agent\Models\Agent;
use Domain\Form\Actions\PruneWorkspaceLeadsAction;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Security\Models\WorkspaceSecurityPolicy;

/**
 * Lead retention: only submitted leads older than the workspace's own window
 * are deleted; other workspaces, recent leads and pending forms stay.
 */
function pwlLead(Agent $agent, int $daysAgo, FormSubmissionStatus $status = FormSubmissionStatus::Submitted): FormSubmission
{
    $definition = FormDefinition::factory()->forAgent($agent)->create();

    return FormSubmission::factory()->forDefinition($definition)->create([
        'status' => $status,
        'submitted_at' => $status === FormSubmissionStatus::Submitted ? now()->subDays($daysAgo) : null,
        'expires_at' => now()->addHour(),
    ]);
}

function pwlSetRetention(Agent $agent, ?int $days): void
{
    WorkspaceSecurityPolicy::forWorkspace($agent->workspace_id)->update(['retention_leads_days' => $days]);
}

it('deletes only that workspace\'s submitted leads past its window', function (): void {
    $agent = Agent::factory()->create();
    pwlSetRetention($agent, 30);
    $old = pwlLead($agent, 45);
    $recent = pwlLead($agent, 10);
    $pending = pwlLead($agent, 45, FormSubmissionStatus::Pending);

    $other = Agent::factory()->create(); // no lead retention set
    $otherOld = pwlLead($other, 400);

    expect(app(PruneWorkspaceLeadsAction::class)->execute())->toBe(1)
        ->and(FormSubmission::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(FormSubmission::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and(FormSubmission::query()->whereKey($pending->id)->exists())->toBeTrue()
        ->and(FormSubmission::query()->whereKey($otherOld->id)->exists())->toBeTrue();
});

it('deletes in batches until nothing is left, and a second run deletes nothing', function (): void {
    $agent = Agent::factory()->create();
    pwlSetRetention($agent, 7);
    foreach (range(1, 5) as $i) {
        pwlLead($agent, 10 + $i);
    }
    $kept = pwlLead($agent, 1);

    expect(app(PruneWorkspaceLeadsAction::class)->execute(batchSize: 2))->toBe(5)
        ->and(app(PruneWorkspaceLeadsAction::class)->execute(batchSize: 2))->toBe(0)
        ->and(FormSubmission::query()->where('workspace_id', $agent->workspace_id)->pluck('id')->all())->toBe([$kept->id]);
});

it('never uses a window below the configured floor', function (): void {
    config(['chatvia.retention.leads_floor_days' => 30]);
    $agent = Agent::factory()->create();
    pwlSetRetention($agent, 1); // saved before the floor was raised
    $lead = pwlLead($agent, 10);

    expect(app(PruneWorkspaceLeadsAction::class)->execute())->toBe(0)
        ->and(FormSubmission::query()->whereKey($lead->id)->exists())->toBeTrue();
});
