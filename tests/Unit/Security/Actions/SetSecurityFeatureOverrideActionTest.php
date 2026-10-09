<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\SetSecurityFeatureOverrideAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Enums\SecurityFeature;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Event;
use Laravel\Pennant\Feature;

it('force-grants the suite to any workspace and audits the change', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $admin = User::factory()->admin()->create();
    $workspace = User::factory()->create();

    app(SetSecurityFeatureOverrideAction::class)->execute(
        $workspace,
        true,
        SecurityActorData::forUser($admin, '203.0.113.7', 'Admin/1.0'),
    );

    expect(WorkspaceSecurityPolicy::forWorkspace($workspace->id)->security_override)->toBeTrue()
        ->and(Feature::for($workspace->fresh())->active(SecurityFeature::Security->value))->toBeTrue();

    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyChanged->value
        && $e->workspaceId === $workspace->id
        && str_contains($e->metadata, 'security_override'));
});

it('is a no-op (no audit event) when the override is unchanged', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $admin = User::factory()->admin()->create();
    $workspace = User::factory()->create();

    // Default is null; setting null again should not write or audit.
    app(SetSecurityFeatureOverrideAction::class)->execute(
        $workspace,
        null,
        SecurityActorData::forUser($admin),
    );

    Event::assertNotDispatched(SecurityEventOccurred::class);
});
