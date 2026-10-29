<?php

use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\StreamedTurnCollector;

/**
 * Folds a turn's StreamEvents into one CompletedTurnData, exactly as an SSE
 * client would experience the turn: deltas concatenate, a guardrail replace
 * overwrites everything streamed so far, and the done/error frames tell how
 * the turn ended, and whether it failed before any text.
 */
it('folds deltas, sources and the done frame into a completed turn, letting a guardrail replace overwrite the text', function (): void {
    $turn = new StreamedTurnCollector()->collect([
        StreamEvent::start('am-1', ['user_message_id' => 'um-1']),
        StreamEvent::delta('am-1', 'Hello'),
        StreamEvent::delta('am-1', ' there!'),
        StreamEvent::sources('am-1', [['id' => 's1', 'label' => 'FAQ']]),
        StreamEvent::done('am-1', 'complete', 420),
    ]);
    $replaced = new StreamedTurnCollector()->collect([
        StreamEvent::start('am-1', []),
        StreamEvent::delta('am-1', 'My card is 4242…'),
        StreamEvent::replace('am-1', 'Sorry, I cannot share that.'),
        StreamEvent::done('am-1', 'complete'),
    ]);

    expect($turn->assistantMessageId)->toBe('am-1')
        ->and($turn->userMessageId)->toBe('um-1')
        ->and($turn->content)->toBe('Hello there!')
        ->and($turn->sources)->toBe([['id' => 's1', 'label' => 'FAQ']])
        ->and($turn->doneReason)->toBe('complete')
        ->and($turn->responseTimeMs)->toBe(420)
        ->and($turn->errorCode)->toBeNull()
        ->and($turn->failedBeforeAnyContent())->toBeFalse()
        ->and($replaced->content)->toBe('Sorry, I cannot share that.');
});

it('marks a turn refused before any text, but keeps the partial text of one that errors after streaming began', function (): void {
    $refused = new StreamedTurnCollector()->collect([
        StreamEvent::start('am-1', []),
        StreamEvent::error('am-1', ErrorCode::MessageLimitReached, 'Limit reached.'),
        StreamEvent::done('am-1', 'error'),
    ]);
    $partial = new StreamedTurnCollector()->collect([
        StreamEvent::start('am-1', []),
        StreamEvent::delta('am-1', 'Half an ans'),
        StreamEvent::error('am-1', ErrorCode::UpstreamUnavailable, 'Upstream closed.'),
        StreamEvent::done('am-1', 'error'),
    ]);

    expect($refused->failedBeforeAnyContent())->toBeTrue()
        ->and($refused->errorCode)->toBe('message_limit_reached')
        ->and($refused->errorMessage)->toBe('Limit reached.')
        ->and($refused->errorRetryable)->toBeFalse()
        ->and($refused->doneReason)->toBe('error')
        ->and($partial->failedBeforeAnyContent())->toBeFalse()
        ->and($partial->content)->toBe('Half an ans')
        ->and($partial->errorRetryable)->toBeTrue();
});
