<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Tools\ExportToolCallAuditAction;
use Domain\Conversation\Data\Audit\ToolCallAuditFiltersData;
use Domain\Conversation\Models\AgentActivity;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Support\Facades\Event;

/**
 * ExportToolCallAuditAction: the header, then the workspace's tool calls
 * newest first with the Governance filters, and one security event for the
 * export.
 */
it('returns the header and the filtered tool calls newest first, and records the export', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $other = createAgent($owner);
    AgentActivity::factory()->forAgent($agent)->create(['tool_name' => 'first']);
    AgentActivity::factory()->forAgent($agent)->create(['tool_name' => 'second']);
    AgentActivity::factory()->forAgent($other)->create(['tool_name' => 'other_agent']);
    AgentActivity::factory()->create(['tool_name' => 'stranger']);

    $rows = app(ExportToolCallAuditAction::class)
        ->execute($owner->workspace()->id, new ToolCallAuditFiltersData(outcome: null, connector: null, agent_id: $agent->id), SecurityActorData::forUser($owner))
        ->all();

    expect($rows[0])->toBe(ExportToolCallAuditAction::HEADER)
        ->and(array_column(array_slice($rows, 1), 3))->toBe(['second', 'first']);

    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === SecurityAuditAction::AuditExported->value);
});
