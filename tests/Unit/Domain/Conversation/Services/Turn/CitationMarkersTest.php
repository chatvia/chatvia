<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Services\Turn\CitationMarkers;
use Domain\Knowledge\Retrieval\Enums\SourceKind;
use Tests\Support\Conversation\TestChatContext;

/**
 * An answer's citation markers are made regular before it is stored:
 * `[1, 3]` becomes `[1][3]`, a marker for a block the model was not given is
 * removed, and code and markdown links are left alone. A spoken answer or a
 * messaging-channel reply loses every marker; an agent that does not cite
 * keeps its text as written.
 */
function cmSource(int $id): RetrievedSourceData
{
    return new RetrievedSourceData($id, "chunk-{$id}", "Passage {$id}", 0.9, "Doc {$id}", SourceKind::Text, null, null, 'source_text', $id);
}

it('makes markers regular and drops the ones for blocks the model was not given', function (): void {
    $markers = new CitationMarkers;

    expect($markers->normalize('Refunds take five days [1, 3]. Shipping is free [9].', 3))->toBe('Refunds take five days[1][3]. Shipping is free.')
        ->and($markers->normalize('See [the guide](https://x.test/1) and `arr[1]`.', 3))->toBe('See [the guide](https://x.test/1) and `arr[1]`.')
        ->and($markers->normalize("```\n\$a[2] = 1;\n```\nDone [2].", 2))->toBe("```\n\$a[2] = 1;\n```\nDone[2].");
});

it('strips every marker from a spoken answer', function (): void {
    expect((new CitationMarkers)->strip('It takes five days [1][2], or one [3, 4].'))->toBe('It takes five days, or one.');
});

it('strips the markers of a reply on a messaging channel', function (): void {
    $citing = Agent::factory()->create();
    $citing->setSetting('sources_display', 'citations', 'appearance');
    $context = TestChatContext::make(agent: $citing->fresh(), source: 'telegram');
    $context->setSources(collect([cmSource(1)]));
    $context->appendStreamedMessage('Open nine to five [1].');

    expect(app(CitationMarkers::class)->clean($context))->toBe('Open nine to five.');
});

it('cleans the answer of an agent that cites, and leaves another agent\'s alone', function (): void {
    $citing = Agent::factory()->create();
    $citing->setSetting('sources_display', 'citations', 'appearance');
    $context = TestChatContext::make(agent: $citing->fresh());
    $context->setSources(collect([cmSource(1), cmSource(2)]));
    $context->appendStreamedMessage('Open nine to five [2, 7].');

    expect(app(CitationMarkers::class)->clean($context))->toBe('Open nine to five[2].')
        ->and($context->streamedMessage)->toBe('Open nine to five[2].');

    $plain = TestChatContext::make(agent: Agent::factory()->create());
    $plain->appendStreamedMessage('Item [1] of the list.');

    expect(app(CitationMarkers::class)->clean($plain))->toBeNull()
        ->and($plain->streamedMessage)->toBe('Item [1] of the list.');
});
