<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\AttachmentSource;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\CitationMarkers;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\ChatFileModel;
use Domain\Llm\Services\LlmProviders;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Conversation\NoToolSelector;
use Tests\Support\Conversation\RecordingGuardrailRecorder;
use Tests\Support\Conversation\RecordingReplyRecorder;
use Tests\Support\Conversation\RedactingOutputGuard;
use Tests\Support\Conversation\TestChatContext;

/**
 * A chat with an attached file sends the file itself inside the streamed
 * request to the model for chat files, as a data: URL, so the model reads
 * it even when it cannot open a link to this site (a local or private
 * install). The real tool loop runs against a mocked HTTP client; the
 * guard, recorders and tool choice are faked.
 */
beforeEach(function (): void {
    Cache::flush();
    Storage::fake(Attachment::DISK);

    $this->sent = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], "data: [DONE]\n\n")]));
    $stack->push(Middleware::history($this->sent));
    app()->instance(Client::class, new Client(['handler' => $stack]));
});

/**
 * Pick a Mistral model that reads PDF files and images as the model for chat files.
 *
 * @param  array<string, mixed>  $facts  more of its models.dev entry
 */
function asFileModel(array $facts = []): Llm
{
    $model = testLlm('mistral-medium-latest', LlmProviderKind::Mistral, [
        'capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text', 'image', 'pdf']], ...$facts],
    ]);
    config(['chatvia.ai.chat_files.enabled' => true, 'chatvia.ai.chat_files.model' => (string) $model->id]);

    return $model;
}

/** The source with the real tool loop and no tools (unless given), guards or saving. */
function attachmentSource(ToolSelector $tools = new NoToolSelector, ?RecordingReplyRecorder $replies = null): AttachmentSource
{
    return new AttachmentSource(
        app(ChatFileModel::class),
        app(LlmProviders::class),
        $replies ?? new RecordingReplyRecorder,
        new RequestDedupeStore,
        $tools,
        app(ToolLoopRunner::class),
        new RedactingOutputGuard,
        new RecordingGuardrailRecorder,
        app(CitationMarkers::class),
    );
}

/**
 * Run one turn with the file attached and return its events.
 *
 * @return list<StreamEvent>
 */
function attachmentTurn(Attachment $file, ToolSelector $tools = new NoToolSelector): array
{
    $events = attachmentSource($tools)->events(TestChatContext::make(message: 'Hvad handler den om?'), [['role' => 'user', 'content' => 'Hvad handler den om?']], collect([$file]));

    return iterator_to_array($events, preserve_keys: false);
}

function attachmentStored(string $filename = 'dania.pdf', string $mimeType = 'application/pdf', string $contents = '%PDF-1.4 Dania'): Attachment
{
    $file = new Attachment(['filepath' => "workspaces/w/chat/{$filename}", 'filename' => $filename, 'mime_type' => $mimeType]);
    Storage::disk(Attachment::DISK)->put($file->filepath, $contents);

    return $file;
}

/** @return array<string, mixed> */
function attachmentSentBody(array $sent): array
{
    return json_decode((string) $sent[0]['request']->getBody(), true);
}

it('sends an attached PDF inside the request as a data: URL, with its name', function (): void {
    asFileModel();

    $events = attachmentTurn(attachmentStored());

    expect(end($events)->data['reason'])->toBe('complete')
        ->and($this->sent)->toHaveCount(1)
        ->and(end(attachmentSentBody($this->sent)['messages']))->toBe([
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'Hvad handler den om?'],
                ['type' => 'document_url', 'document_url' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 Dania'), 'document_name' => 'dania.pdf'],
            ],
        ]);
});

it('sends an attached image as an image part', function (): void {
    asFileModel();

    attachmentTurn(attachmentStored('receipt.png', 'image/png', 'PNG bytes'));

    expect(end(attachmentSentBody($this->sent)['messages'])['content'][1])
        ->toBe(['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.base64_encode('PNG bytes')]]);
});

it('answers with the agent\'s own model when it reads PDF files and images', function (): void {
    asFileModel();
    $own = testLlm('mistral-large-2512', LlmProviderKind::Mistral, ['capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text', 'image', 'pdf']]]]);

    iterator_to_array(attachmentSource()->events(TestChatContext::make(llm: $own), [['role' => 'user', 'content' => 'Hello']], collect([attachmentStored()])), false);

    expect(attachmentSentBody($this->sent)['model'])->toBe('mistral-large-2512');
});

it('answers with the platform\'s model for chat files when the agent\'s own model cannot read files', function (): void {
    asFileModel();
    $own = testLlm('mistral-text-only', LlmProviderKind::Mistral, ['capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text']]]]);

    iterator_to_array(attachmentSource()->events(TestChatContext::make(llm: $own), [['role' => 'user', 'content' => 'Hello']], collect([attachmentStored()])), false);

    expect(attachmentSentBody($this->sent)['model'])->toBe('mistral-medium-latest');
});

it('ends the turn with an error, without asking the model, when storage cannot read the file', function (): void {
    asFileModel();
    $file = new Attachment(['filepath' => 'workspaces/w/chat/gone.pdf', 'filename' => 'gone.pdf', 'mime_type' => 'application/pdf']);

    $events = attachmentTurn($file);

    expect(array_map(static fn (StreamEvent $e): EventType => $e->type, $events))->toBe([EventType::Start, EventType::Error, EventType::Done])
        ->and($events[1]->data['message'])->toBe('Something went wrong while answering from the file.')
        ->and($this->sent)->toBe([]);
});

it('ends the turn with an error when the file is of a type no chat file may be', function (): void {
    asFileModel();

    $events = attachmentTurn(attachmentStored('notes.txt', 'text/plain', 'notes'));

    expect($events[1]->data['message'])->toBe('The AI model cannot read this type of file. Start a new chat to attach another file.')
        ->and($this->sent)->toBe([]);
});

it('fits the agent\'s settings to what the model for chat files takes', function (): void {
    asFileModel(['temperature' => false, 'limit' => ['output' => 32768]]);

    attachmentTurn(attachmentStored());

    $body = attachmentSentBody($this->sent);

    expect($body['model'])->toBe('mistral-medium-latest')
        ->and($body)->not->toHaveKeys(['temperature', 'top_p', 'top_k', 'presence_penalty', 'frequency_penalty']);
});

it('offers tools only to a model for chat files that can call them', function (?bool $callsTools, bool $offered): void {
    asFileModel(array_filter(['tool_call' => $callsTools], static fn (mixed $value): bool => $value !== null));

    attachmentTurn(attachmentStored(), new class extends ToolSelector
    {
        public function __construct() {}

        public function selectForTurn(Agent $agent, string $userMessage, ?string $conversationId): array
        {
            return [['type' => 'function', 'function' => ['name' => 'lookup_order', 'parameters' => ['type' => 'object', 'properties' => new stdClass]]]];
        }

        public function selectForVisitorTurn(Agent $agent, string $userMessage, ?string $conversationId): array
        {
            return $this->selectForTurn($agent, $userMessage, $conversationId);
        }
    });

    expect(isset(attachmentSentBody($this->sent)['tools']))->toBe($offered);
})->with([
    'calls tools' => [true, true],
    'not said' => [null, true],
    'calls none' => [false, false],
]);

it('ends the turn with a clear error, saving nothing, while chat file uploads are off', function (): void {
    asFileModel();
    config(['chatvia.ai.chat_files.enabled' => false]);
    $replies = new RecordingReplyRecorder;

    $events = iterator_to_array(attachmentSource(replies: $replies)->events(TestChatContext::make(), [['role' => 'user', 'content' => 'Hello']], collect([attachmentStored()])), false);

    expect(array_map(static fn (StreamEvent $e): EventType => $e->type, $events))->toBe([EventType::Start, EventType::Error, EventType::Done])
        ->and($events[1]->data['message'])->toBe('Chatting with files is not available.')
        ->and($replies->saved)->toBe([])
        ->and($this->sent)->toBe([]);
});
