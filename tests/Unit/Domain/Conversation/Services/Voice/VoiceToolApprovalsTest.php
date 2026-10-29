<?php

use Domain\Conversation\Services\Voice\VoiceToolApprovals;

/**
 * The actions a voice call holds for the visitor's yes ({@see VoiceToolApprovals}):
 * a held call is confirmed only by the same call in the reply to the
 * visitor's very next words, and an answered question is dropped once they
 * speak again.
 */
it('confirms a held call only in the reply to the visitor\'s next words, and drops it once they speak again', function (): void {
    $approvals = new VoiceToolApprovals;
    $approvals->hold('send', '{"to":"a","body":"hi"}');
    $early = $approvals->isConfirmedBy('item_1', 'send', '{"to":"a","body":"hi"}');
    $approvals->visitorSpoke('item_2');

    expect($early)->toBeFalse()
        ->and($approvals->isAnswer('item_2'))->toBeTrue()
        ->and($approvals->isConfirmedBy('item_2', 'send', '{"body": "hi", "to": "a"}'))->toBeTrue()
        ->and($approvals->isConfirmedBy('item_2', 'send', '{"to":"b","body":"hi"}'))->toBeFalse()
        ->and($approvals->isConfirmedBy('item_2', 'other', '{"to":"a","body":"hi"}'))->toBeFalse();

    $approvals->visitorSpoke('item_3');

    expect($approvals->isConfirmedBy('item_3', 'send', '{"to":"a","body":"hi"}'))->toBeFalse()
        ->and($approvals->isAnswer('item_3'))->toBeFalse();
});

it('needs a new yes once a call has run', function (): void {
    $approvals = new VoiceToolApprovals;
    $approvals->hold('send', '{"items":[2,1]}');
    $approvals->visitorSpoke('item_2');

    expect($approvals->isConfirmedBy('item_2', 'send', '{"items":[1,2]}'))->toBeFalse();

    $approvals->release('send', '{"items":[2,1]}');

    expect($approvals->isConfirmedBy('item_2', 'send', '{"items":[2,1]}'))->toBeFalse();
});
