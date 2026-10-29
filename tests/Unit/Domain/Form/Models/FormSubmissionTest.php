<?php

use Domain\Form\Models\FormSubmission;

/**
 * The checksum of a submission's answers, which lets the same answers from a
 * second render of a form be recognised: it ignores key order, tells
 * different answers apart, and is null when there are none.
 */
it('fingerprints answers by content, independent of key order, and none without answers', function (): void {
    $a = FormSubmission::checksum(['email' => 'a@b.com', 'name' => 'Jane']);
    $b = FormSubmission::checksum(['name' => 'Jane', 'email' => 'a@b.com']);

    expect($a)->toBe($b)
        ->and(strlen($a))->toBe(64)
        ->and(FormSubmission::checksum(['email' => 'c@d.com', 'name' => 'Jane']))->not->toBe($a)
        ->and(FormSubmission::checksum([]))->toBeNull()
        ->and(FormSubmission::checksum(null))->toBeNull();
});
