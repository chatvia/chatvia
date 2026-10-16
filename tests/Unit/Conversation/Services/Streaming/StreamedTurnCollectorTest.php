<?php

use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\StreamedTurnCollector;

/*
|--------------------------------------------------------------------------
| StreamedTurnCollector
|--------------------------------------------------------------------------
|
| Folds a turn's StreamEvents into one CompletedTurnData, exactly as an SSE
| client would experience the turn: deltas concatenate, a guardrail replace
| overwrites everything streamed so far, and the done/error frames tell how
| the turn ended.
*/

it('folds deltas, sources and the done frame into a completed turn', function (): void {
    $turn = new StreamedTurnCollector()->collect([
        StreamEvent::start('am-1', ['user_message_id' => 'um-1']),
        StreamEvent::delta('am-1', 'Hello'),
        StreamEvent::delta('am-1', ' there!'),
        StreamEvent::sources('am-1', [['id' => 's1', 'label' => 'FAQ']]),
        StreamEvent::done('am-1', 'complete', 420),
    ]);

    expect($turn->assistantMessageId)->toBe('am-1')
        ->and($turn->userMessageId)->toBe('um-1')
        ->and($turn->content)->toBe('Hello there!')
        ->and($turn->sources)->toBe([['id' => 's1', 'label' => 'FAQ']])
        ->and($turn->doneReason)->toBe('complete')
        ->and($turn->responseTimeMs)->toBe(420)
        ->and($turn->errorCode)->toBeNull()
        ->and($turn->failedBeforeAnyContent())->toBeFalse();
});

it('lets a guardrail replace overwrite the streamed text', function (): void {
    $turn = new StreamedTurnCollector()->collect([
        StreamEvent::start('am-1', []),
        StreamEvent::delta('am-1', 'My card is 4242…'),
        StreamEvent::replace('am-1', 'Sorry, I cannot share that.'),
        StreamEvent::done('am-1', 'complete'),
    ]);

    expect($turn->content)->toBe('Sorry, I cannot share that.');
});

it('marks a turn refused before any text so the API can answer an error status', function (): void {
    $turn = new StreamedTurnCollector()->collect([
        StreamEvent::start('am-1', []),
        StreamEvent::error('am-1', ErrorCode::MessageLimitReached, 'Limit reached.'),
        StreamEvent::done('am-1', 'error'),
    ]);

    expect($turn->failedBeforeAnyContent())->toBeTrue()
        ->and($turn->errorCode)->toBe('message_limit_reached')
        ->and($turn->errorMessage)->toBe('Limit reached.')
        ->and($turn->errorRetryable)->toBeFalse()
        ->and($turn->doneReason)->toBe('error');
});

it('keeps partial text when the turn errors after streaming began', function (): void {
    $turn = new StreamedTurnCollector()->collect([
        StreamEvent::start('am-1', []),
        StreamEvent::delta('am-1', 'Half an ans'),
        StreamEvent::error('am-1', ErrorCode::UpstreamUnavailable, 'Upstream closed.'),
        StreamEvent::done('am-1', 'error'),
    ]);

    expect($turn->failedBeforeAnyContent())->toBeFalse()
        ->and($turn->content)->toBe('Half an ans')
        ->and($turn->errorRetryable)->toBeTrue();
});
