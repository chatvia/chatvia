<?php

use Domain\Form\Models\FormSubmission;

it('fingerprints answers stably, independent of key order', function (): void {
    $a = FormSubmission::checksum(['email' => 'a@b.com', 'name' => 'Jane']);
    $b = FormSubmission::checksum(['name' => 'Jane', 'email' => 'a@b.com']);

    expect($a)->toBe($b)->and(strlen($a))->toBe(64); // HMAC-SHA256 hex
});

it('produces a different fingerprint for different answers', function (): void {
    expect(FormSubmission::checksum(['email' => 'a@b.com']))
        ->not->toBe(FormSubmission::checksum(['email' => 'c@d.com']));
});

it('returns null for an empty answer set', function (): void {
    expect(FormSubmission::checksum([]))->toBeNull()
        ->and(FormSubmission::checksum(null))->toBeNull();
});
