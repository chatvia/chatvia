<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\PdfSource;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\DocumentLlm;
use Domain\Llm\Services\ModelsDevCatalog;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Conversation\NoToolSelector;
use Tests\Support\Conversation\RecordingGuardrailRecorder;
use Tests\Support\Conversation\RecordingReplyRecorder;
use Tests\Support\Conversation\RedactingOutputGuard;
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

/** The PDF source on Mistral (unless given), with the real tool loop and no tools (unless given), guards or saving. */
function pdfSource(ToolSelector $tools = new NoToolSelector, ?RecordingReplyRecorder $replies = null, ?DocumentLlm $documents = null): PdfSource
{
    return new PdfSource(
        $documents ?? new DocumentLlm(LlmProviderKind::Mistral),
        $replies ?? new RecordingReplyRecorder,
        new RequestDedupeStore,
        $tools,
        app(ToolLoopRunner::class),
        new RedactingOutputGuard,
        new RecordingGuardrailRecorder,
    );
}

/**
 * Run one PDF turn and return its events.
 *
 * @return list<StreamEvent>
 */
function pdfTurn(Attachment $pdf, ToolSelector $tools = new NoToolSelector): array
{
    $events = pdfSource($tools)->events(TestChatContext::make(message: 'Hvad handler den om?'), [['role' => 'user', 'content' => 'Hvad handler den om?']], collect([$pdf]));

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

/**
 * Make models.dev say this about the PDF chat model.
 *
 * @param  array<string, mixed>  $entry
 */
function pdfModelFacts(array $entry): void
{
    config(['chatvia.ai.models_dev.enabled' => true, 'chatvia.ai.models.document_chat' => 'mistral-medium-latest']);
    Http::fake([ModelsDevCatalog::URL => Http::response(['providers' => ['mistral' => ['models' => ['mistral-medium-latest' => $entry]]]])]);
    app(ModelsDevCatalog::class)->refresh();
}

function pdfStoredPdf(): Attachment
{
    $pdf = new Attachment(['filepath' => 'workspaces/w/chat/dania.pdf', 'filename' => 'dania.pdf', 'mime_type' => 'application/pdf']);
    Storage::disk(Attachment::DISK)->put($pdf->filepath, '%PDF-1.4 Dania');

    return $pdf;
}

it('fits the agent\'s settings to what the PDF chat model takes', function (): void {
    pdfModelFacts(['reasoning' => false, 'temperature' => false, 'limit' => ['output' => 32768]]);

    pdfTurn(pdfStoredPdf());

    $body = json_decode((string) $this->sent[0]['request']->getBody(), true);

    expect($body['model'])->toBe('mistral-medium-latest')
        ->and($body)->not->toHaveKeys(['temperature', 'top_p', 'top_k', 'presence_penalty', 'frequency_penalty']);
});

it('offers tools only to a PDF chat model that can call them', function (?bool $callsTools, bool $offered): void {
    pdfModelFacts(array_filter(['reasoning' => false, 'tool_call' => $callsTools], static fn (mixed $value): bool => $value !== null));

    pdfTurn(pdfStoredPdf(), new class extends ToolSelector
    {
        public function __construct() {}

        public function selectForTurn(Agent $agent, string $userMessage, ?string $conversationId): array
        {
            return [['type' => 'function', 'function' => ['name' => 'lookup_order', 'parameters' => ['type' => 'object', 'properties' => new stdClass]]]];
        }
    });

    $body = json_decode((string) $this->sent[0]['request']->getBody(), true);

    expect(isset($body['tools']))->toBe($offered);
})->with([
    'calls tools' => [true, true],
    'not said' => [null, true],
    'calls none' => [false, false],
]);

it('ends a PDF turn with a clear error, saving nothing, when no LLM can read PDF documents', function (): void {
    $replies = new RecordingReplyRecorder;

    $events = iterator_to_array(pdfSource(replies: $replies, documents: new DocumentLlm(null))->events(TestChatContext::make(), [['role' => 'user', 'content' => 'Hello']], collect()), false);

    expect(array_map(static fn (StreamEvent $e): EventType => $e->type, $events))->toBe([EventType::Start, EventType::Error, EventType::Done])
        ->and($events[1]->data['message'])->toBe('Chatting with PDF files is not available.')
        ->and($events[1]->data['message'])->not->toContain('Mistral')
        ->and($replies->saved)->toBe([])
        ->and($this->sent)->toBe([]);
});
