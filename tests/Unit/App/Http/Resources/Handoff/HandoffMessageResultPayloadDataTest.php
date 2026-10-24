<?php

use App\Http\Resources\Handoff\HandoffMessageResultPayloadData;
use Domain\Conversation\Models\Message;

/** The dashboard learns an operator reply by its public id, never its internal key. */
it('names the reply by its public id', function (): void {
    $message = Message::factory()->create();

    expect(HandoffMessageResultPayloadData::fromMessage($message)->id)->toBe($message->external_id)
        ->and($message->external_id)->not->toBe((string) $message->id);
});
