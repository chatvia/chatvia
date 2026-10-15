<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\RecordSecurityEventAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Models\SecurityAuditLog;

/**
 * The recorder writes one row per security event into `security_audit_log`.
 * Guarantees under test: denormalized causer name/email are stored, sensitive
 * metadata keys are redacted, oversized payloads are capped, a sha256 of the
 * RAW payload is kept, and the row has no updated_at.
 */
it('writes one row with denormalized causer, redacted metadata + raw hash', function (): void {
    $user = User::factory()->create();

    $event = SecurityEventOccurred::for(
        action: SecurityAuditAction::ApiKeyCreated,
        workspaceId: $user->workspace()->id,
        actor: SecurityActorData::forUser($user, '203.0.113.7', 'Mozilla/5.0'),
        targetType: 'api_token',
        targetId: 42,
        targetLabel: 'CLI key',
        metadata: ['name' => 'CLI key', 'secret' => 'topsecret'],
    );

    $row = app(RecordSecurityEventAction::class)->execute($event);

    expect($row)->toBeInstanceOf(SecurityAuditLog::class)
        ->and((int) $row->workspace_id)->toBe($user->workspace()->id)
        ->and((int) $row->user_id)->toBe($user->id)
        ->and($row->user_name)->toBe($user->name)
        ->and($row->user_email)->toBe($user->email)
        ->and($row->action)->toBe('api_key.created')
        ->and($row->target_type)->toBe('api_token')
        ->and($row->target_id)->toBe('42')
        ->and($row->target_label)->toBe('CLI key')
        ->and($row->ip)->toBe('203.0.113.7')
        ->and($row->outcome)->toBe('success')
        ->and($row->metadata['name'])->toBe('CLI key')
        ->and($row->metadata['secret'])->toBe('[redacted]')      // secret never stored
        ->and($row->metadata_sha256)->toBe(hash('sha256', $event->metadata))
        ->and($row->metadata_truncated)->toBeFalse();
});

it('caps oversized metadata and flags truncation, hashing the raw payload', function (): void {
    config(['chatvia.security_audit.max_metadata_bytes' => 20]);
    $user = User::factory()->create();

    $event = SecurityEventOccurred::for(
        action: SecurityAuditAction::PolicyChanged,
        workspaceId: $user->workspace()->id,
        actor: SecurityActorData::forUser($user),
        metadata: ['blob' => str_repeat('x', 200)],
    );

    $row = app(RecordSecurityEventAction::class)->execute($event);

    expect($row->metadata_truncated)->toBeTrue()
        ->and($row->metadata_sha256)->toBe(hash('sha256', $event->metadata)); // hash over RAW, pre-cap
});

it('writes a row without an updated_at column', function (): void {
    $user = User::factory()->create();

    $row = app(RecordSecurityEventAction::class)->execute(SecurityEventOccurred::for(
        action: SecurityAuditAction::AuthLogin,
        workspaceId: $user->workspace()->id,
        actor: SecurityActorData::forUser($user),
    ));

    expect($row->getAttributes())->not->toHaveKey('updated_at');
});
