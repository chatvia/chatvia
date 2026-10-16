<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\PdfSource;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\DocumentLlm;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Conversation\NoToolSelector;
use Tests\Support\Conversation\TestChatContext;

/**
 * Chat with a PDF sends the PDF itself inside the streamed request, as a
 * data: URL, so the document LLM reads the file even when it cannot open a
 * link to this site (a local or private install). The real tool loop runs
 * against a mocked HTTP client; the guard, recorders and tool choice are
 * faked.
 */
beforeEach(function (): void {
    Cache::flush();
    Storage::fake(Attachment::DISK);
    connectAiProviders(AiProvider::Mistral);

    $this->sent = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], "data: [DONE]\n\n")]));
    $stack->push(Middleware::history($this->sent));
    app()->instance(Client::class, new Client(['handler' => $stack]));
});

/** The PDF source on Mistral, with the real tool loop and no tools, guards or saving. */
function pdfSource(): PdfSource
{
    return new PdfSource(
        new DocumentLlm(LlmProviderKind::Mistral),
        new class extends AssistantReplyRecorder
        {
            public function __construct() {}

            public function recordCompletedReply(ChatContext $context): void {}
        },
        new RequestDedupeStore,
        new NoToolSelector,
        app(ToolLoopRunner::class),
        new class extends GuardrailService
        {
            public function __construct() {}

            public function inspectOutput(Agent $agent, string $text, ?string $language = null): GuardResultData
            {
                return GuardResultData::clean($text);
            }
        },
        new class extends GuardrailRecorder
        {
            public function __construct() {}

            public function record(ChatContext $context, string $sample, GuardResultData $result): void {}
        },
    );
}

/**
 * Run one PDF turn and return its events.
 *
 * @return list<StreamEvent>
 */
function pdfTurn(Attachment $pdf): array
{
    $events = pdfSource()->events(TestChatContext::make(message: 'Hvad handler den om?'), [['role' => 'user', 'content' => 'Hvad handler den om?']], collect([$pdf]));

    return iterator_to_array($events, preserve_keys: false);
}

it('sends the attached PDF inside the request as a data: URL', function (): void {
    $pdf = new Attachment(['filepath' => 'workspaces/w/chat/dania.pdf', 'filename' => 'dania.pdf', 'mime_type' => 'application/pdf']);
    Storage::disk(Attachment::DISK)->put($pdf->filepath, '%PDF-1.4 Dania');

    $events = pdfTurn($pdf);

    $body = json_decode((string) $this->sent[0]['request']->getBody(), true);

    expect(end($events)->data['reason'])->toBe('complete')
        ->and($this->sent)->toHaveCount(1)
        ->and(end($body['messages']))->toBe([
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'Hvad handler den om?'],
                ['type' => 'document_url', 'document_url' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 Dania')],
            ],
        ]);
});

it('ends the turn with an error, without asking the model, when storage cannot read the PDF', function (): void {
    $pdf = new Attachment(['filepath' => 'workspaces/w/chat/gone.pdf', 'filename' => 'gone.pdf', 'mime_type' => 'application/pdf']);

    $events = pdfTurn($pdf);

    expect(array_map(static fn (StreamEvent $e): EventType => $e->type, $events))->toBe([EventType::Start, EventType::Error, EventType::Done])
        ->and($events[1]->data['message'])->toBe('Something went wrong while answering from the PDF.')
        ->and($this->sent)->toBe([]);
});
