<?php

use App\Identity\Workspace\Models\User;
use Domain\Guardrail\Actions\PruneGuardrailEventsAction;
use Domain\Guardrail\Models\GuardrailEvent;

function guardrailRow(User $workspace, int $daysAgo): void
{
    GuardrailEvent::query()->create([
        'agent_name' => 'Bot',
        'workspace_id' => $workspace->workspace()->id,
        'stage' => 'input',
        'guard_type' => 'pii',
        'outcome' => 'redacted',
        'created_at' => now()->subDays($daysAgo),
    ]);
}

it('deletes guardrail events older than the window', function (): void {
    $workspace = User::factory()->create();
    guardrailRow($workspace, 100);
    guardrailRow($workspace, 10);

    expect(app(PruneGuardrailEventsAction::class)->execute(90))->toBe(1)
        ->and(GuardrailEvent::query()->count())->toBe(1);
});

it('deletes in batches until every old event is gone', function (): void {
    $workspace = User::factory()->create();
    guardrailRow($workspace, 100);
    guardrailRow($workspace, 120);
    guardrailRow($workspace, 150);
    guardrailRow($workspace, 10);

    expect(app(PruneGuardrailEventsAction::class)->execute(90, batchSize: 1))->toBe(3)
        ->and(GuardrailEvent::query()->where('workspace_id', $workspace->workspace()->id)->count())->toBe(1);
});
