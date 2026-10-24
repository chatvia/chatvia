<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\ExportSecurityAuditLogAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Data\SecurityAuditFiltersData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\SecurityAuditLog;
use Illuminate\Support\Facades\Event;

/**
 * ExportSecurityAuditLogAction: the header, then the workspace's rows newest
 * first with the viewer's filters, and one security event for the export.
 */
it('returns the header and the filtered rows newest first, and records the export', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $owner = User::factory()->create();
    $workspaceId = $owner->workspace()->id;
    SecurityAuditLog::create(['workspace_id' => $workspaceId, 'action' => SecurityAuditAction::AuthLogin->value, 'outcome' => 'success', 'user_name' => 'First']);
    SecurityAuditLog::create(['workspace_id' => $workspaceId, 'action' => SecurityAuditAction::AuthLogin->value, 'outcome' => 'failure', 'user_name' => 'Second']);
    SecurityAuditLog::create(['workspace_id' => $workspaceId, 'action' => SecurityAuditAction::PolicyChanged->value, 'outcome' => 'success', 'user_name' => 'Policy']);

    $rows = app(ExportSecurityAuditLogAction::class)
        ->execute($workspaceId, new SecurityAuditFiltersData(category: 'auth', outcome: null), SecurityActorData::forUser($owner))
        ->all();

    expect($rows[0])->toBe(ExportSecurityAuditLogAction::HEADER)
        ->and(array_column(array_slice($rows, 1), 5))->toBe(['Second', 'First']);

    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $event): bool => $event->action === SecurityAuditAction::AuditExported->value
        && $event->workspaceId === $workspaceId);
});
