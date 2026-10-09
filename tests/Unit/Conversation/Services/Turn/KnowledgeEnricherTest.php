<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\RetrievedSourceData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Turn\KnowledgeEnricher;
use Domain\Knowledge\Ingestion\Services\ChunkImageLink;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;

/**
 * Knowledge is attached to the LLM-facing message as numbered reference
 * material, with a signed link for every described image.
 */
function enricherContext(Agent $agent, string $question): ChatContext
{
    $request = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: Llm::factory()->create(), message: $question),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
    );

    return new ChatContext(
        requestData: $request,
        payload: new UserTurnData(
            external_session_id: $request->externalSessionId,
            external_conversation_id: $request->conversationId,
            external_message_id: (string) Str::uuid(),
            model: 'test-model',
            message: $question,
            sent_at: now(),
            message_for_llm: $question,
        ),
        llmMessages: [],
        userMessageId: (string) Str::uuid(),
        assistantMessageId: (string) Str::uuid(),
    );
}

function enricherChunk(int $id, string $externalId, string $text, float $confidence, array $images = []): RetrievedChunkData
{
    return new RetrievedChunkData(
        id: $id,
        external_id: $externalId,
        chunk: $text,
        chunk_index: 0,
        chunkable_type: 'source_text',
        chunkable_id: 999_999,
        metadata: json_encode(['images' => $images]),
        search_type: 'vector+bm25',
        score: 0.03,
        confidence_score: $confidence,
    );
}

it('adds the retrieved chunks as reference material to the LLM-facing message', function (): void {
    $agent = Agent::factory()->create();
    $imageChunk = '22222222-2222-2222-2222-222222222222';

    $knowledgeBase = Mockery::mock(KnowledgeBase::class);
    $knowledgeBase->shouldReceive('search')->andReturn([
        enricherChunk(1, '11111111-1111-1111-1111-111111111111', "First chunk <tag> & text\nline2", 0.9),
        enricherChunk(2, $imageChunk, 'Second chunk', 0.7, ['a.png' => 'A [chart] (x)', 'b.png' => "Logo\nnew"]),
    ]);
    app()->instance(KnowledgeBase::class, $knowledgeBase);

    $context = app(KnowledgeEnricher::class)->enrich(enricherContext($agent, 'What is <b>the</b> price & cost?'));

    $url = fn (int $index): string => rtrim((string) config('app.url'), '/').ChunkImageLink::path($imageChunk, $index);

    expect($context->payload->message_for_llm)->toBe(
        "### User Question:\nWhat is <b>the</b> price & cost?\n\n### Reference material\n\n"
        ."⚠️ CRITICAL INSTRUCTION: This material includes images. You MUST include relevant images in your response using markdown format: ![description](url)\n\n"
        ."[0]:\nFirst chunk <tag> & text\nline2\n\n"
        ."[1]:\nSecond chunk\n\n"
        ."📷 IMAGES FOR THIS ITEM (REQUIRED - Include in response):\n"
        ."- Image 0: A  chart   x \n  Markdown: ![A  chart   x ](".$url(0).")\n"
        ."- Image 1: Logo new\n  Markdown: ![Logo new](".$url(1).")\n\n"
    )
        ->and($context->payload->message)->toBe('What is <b>the</b> price & cost?')
        ->and($context->confidenceScore)->toBe(0.8)
        ->and($context->sources)->toHaveCount(2)
        ->and($context->sources->first())->toBeInstanceOf(RetrievedSourceData::class);
});

it('keeps the source list shape the stream and the cache rely on', function (): void {
    $agent = Agent::factory()->create();

    $knowledgeBase = Mockery::mock(KnowledgeBase::class);
    $knowledgeBase->shouldReceive('search')->andReturn([enricherChunk(5, 'ext-5', 'Only text', 0.5)]);
    app()->instance(KnowledgeBase::class, $knowledgeBase);

    $context = app(KnowledgeEnricher::class)->enrich(enricherContext($agent, 'Hours?'));

    expect($context->sources->toArray())->toBe([[
        'id' => 5,
        'external_id' => 'ext-5',
        'content' => 'Only text',
        'confidence_score' => 0.5,
        'metadata' => [
            'filename' => 'Unknown',
            'document_id' => 5,
            'chunkable_type' => 'source_text',
            'chunkable_id' => 999_999,
        ],
        'images' => [],
    ]])
        ->and($context->payload->message_for_llm)->toBe("### User Question:\nHours?\n\n### Reference material\n[0]:\nOnly text\n");
});

it('leaves the message alone when nothing is found', function (): void {
    $agent = Agent::factory()->create();

    $knowledgeBase = Mockery::mock(KnowledgeBase::class);
    $knowledgeBase->shouldReceive('search')->andReturn([]);
    app()->instance(KnowledgeBase::class, $knowledgeBase);

    $context = app(KnowledgeEnricher::class)->enrich(enricherContext($agent, 'Hi'));

    expect($context->payload->message_for_llm)->toBe('Hi')
        ->and($context->sources)->toBeEmpty()
        ->and($context->confidenceScore)->toBeNull();
});
