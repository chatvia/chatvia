<?php

use App\Identity\Workspace\Models\Workspace;
use Domain\Security\Models\BlockedVisitor;
use Domain\Security\Services\VisitorBlocklist;
use Domain\Security\Services\VisitorFingerprint;

/**
 * A visitor is blocked while a block for their fingerprint is in force in
 * that workspace: not after it ended, and not in another workspace.
 */
beforeEach(function (): void {
    $this->workspace = Workspace::factory()->create();
    $this->blocklist = app(VisitorBlocklist::class);
    $this->key = app(VisitorFingerprint::class)->for($this->workspace->id, '198.51.100.4', 'Firefox/130');
});

it('blocks a visitor with a block in force', function (?string $ends): void {
    BlockedVisitor::factory()->create([
        'workspace_id' => $this->workspace->id,
        'visitor_key' => $this->key,
        'expires_at' => $ends === null ? null : now()->modify($ends),
    ]);

    expect($this->blocklist->blocks($this->workspace->id, '198.51.100.4', 'Firefox/130'))->toBeTrue()
        ->and($this->blocklist->blocks($this->workspace->id, '198.51.100.4', 'Chrome/129'))->toBeFalse();
})->with(['until unblocked' => [null], 'for a week' => ['+1 week']]);

it('lets the visitor chat once the block ended', function (): void {
    BlockedVisitor::factory()->expired()->create(['workspace_id' => $this->workspace->id, 'visitor_key' => $this->key]);

    expect($this->blocklist->blocks($this->workspace->id, '198.51.100.4', 'Firefox/130'))->toBeFalse();
});

it('does not block the same visitor in another workspace', function (): void {
    BlockedVisitor::factory()->create(['workspace_id' => $this->workspace->id, 'visitor_key' => $this->key]);
    $other = Workspace::factory()->create();

    expect($this->blocklist->blocks($other->id, '198.51.100.4', 'Firefox/130'))->toBeFalse()
        ->and($this->blocklist->blocks($this->workspace->id, null, 'Firefox/130'))->toBeFalse();
});
