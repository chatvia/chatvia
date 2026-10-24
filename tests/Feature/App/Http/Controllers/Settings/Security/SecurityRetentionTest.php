<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Models\WorkspaceSecurityPolicy;

/**
 * The retention windows on the workspace's Security page (security.update):
 * each window is saved, a blank one means "not set", and each sits between
 * its floor and its ceiling (the install's conversation retention, the
 * mandated audit floor, the install maximum). Anonymizing may not come later
 * than the conversation retention sent with it.
 */
beforeEach(function (): void {
    withEnterpriseLicense();
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

/** A security form that changes only the given retention fields. */
function srtForm(array $retention): array
{
    return [
        'ip_allowlist_mode' => 'off',
        'ip_allowlist' => [],
        'geo_mode' => 'off',
        'geo_countries' => [],
        ...$retention,
    ];
}

function srtPolicy(User $owner): WorkspaceSecurityPolicy
{
    return WorkspaceSecurityPolicy::forWorkspace($owner->workspace()->id)->fresh();
}

it('saves every retention window', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), srtForm([
            'retention_conversations_days' => 90,
            'retention_audit_days' => 90,
            'retention_leads_days' => 365,
            'retention_analytics_days' => 180,
            'anonymize_after_days' => 30,
        ]))
        ->assertSessionHasNoErrors();

    expect(srtPolicy($this->owner))
        ->retention_conversations_days->toBe(90)
        ->retention_audit_days->toBe(90)
        ->retention_leads_days->toBe(365)
        ->retention_analytics_days->toBe(180)
        ->anonymize_after_days->toBe(30);
});

it('treats blank lead, analytics and anonymize windows as not set', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'retention_leads_days' => 90,
        'retention_analytics_days' => 90,
        'anonymize_after_days' => 30,
    ]);

    $this->actingAs($this->owner)
        ->put(route('security.update'), srtForm([
            'retention_leads_days' => '',
            'retention_analytics_days' => '',
            'anonymize_after_days' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect(srtPolicy($this->owner))
        ->retention_leads_days->toBeNull()
        ->retention_analytics_days->toBeNull()
        ->anonymize_after_days->toBeNull();
});

it('rejects a retention window outside its floor and ceiling', function (string $field, int $days): void {
    config([
        'chatvia.retention.conversations_days' => 366,
        'chatvia.retention.leads_floor_days' => 1,
        'chatvia.retention.analytics_floor_days' => 30,
        'chatvia.retention.anonymize_floor_days' => 1,
        'chatvia.retention.max_days' => 3650,
        'chatvia.security_audit.retention_floor_days' => 30,
    ]);

    $this->actingAs($this->owner)
        ->put(route('security.update'), srtForm([$field => $days]))
        ->assertSessionHasErrors($field);

    expect(srtPolicy($this->owner)->{$field})->toBeNull();
})->with([
    'conversations above the install retention' => ['retention_conversations_days', 367],
    'audit below the mandated floor' => ['retention_audit_days', 5],
    'leads below 1 day' => ['retention_leads_days', 0],
    'leads above the maximum' => ['retention_leads_days', 3651],
    'analytics below 30 days' => ['retention_analytics_days', 29],
    'analytics above the maximum' => ['retention_analytics_days', 3651],
    'anonymize below 1 day' => ['anonymize_after_days', 0],
    'anonymize above the install conversation retention' => ['anonymize_after_days', 367],
]);

it('accepts the edge values of each window', function (): void {
    config([
        'chatvia.retention.conversations_days' => 366,
        'chatvia.retention.analytics_floor_days' => 30,
        'chatvia.retention.max_days' => 3650,
    ]);

    $this->actingAs($this->owner)
        ->put(route('security.update'), srtForm([
            'retention_leads_days' => 3650,
            'retention_analytics_days' => 30,
            'anonymize_after_days' => 366,
        ]))
        ->assertSessionHasNoErrors();
});

it('rejects anonymizing later than the conversation retention sent with it', function (int $anonymizeAfter, bool $accepted): void {
    $response = $this->actingAs($this->owner)
        ->put(route('security.update'), srtForm([
            'retention_conversations_days' => 30,
            'anonymize_after_days' => $anonymizeAfter,
        ]));

    $accepted ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('anonymize_after_days');
})->with([
    'a day later' => [31, false],
    'on the same day' => [30, true],
]);
