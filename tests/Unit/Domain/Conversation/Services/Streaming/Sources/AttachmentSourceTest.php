<?php

use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\AttachmentSource;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
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
 * install); a file storage cannot read, or uploads switched off, end the
 * turn with an error before any model call. The real tool loop runs against
 * a mocked HTTP client; the guard, recorders and tool choice are faked.
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

/** The source with the real tool loop and no tools, guards or saving. */
function attachmentSource(?RecordingReplyRecorder $replies = null): AttachmentSource
{
    return new AttachmentSource(
        app(ChatFileModel::class),
        app(LlmProviders::class),
        $replies ?? new RecordingReplyRecorder,
        new RequestDedupeStore,
        new NoToolSelector,
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
function attachmentTurn(Attachment $file): array
{
    $events = attachmentSource()->events(TestChatContext::make(message: 'Hvad handler den om?'), [['role' => 'user', 'content' => 'Hvad handler den om?']], collect([$file]));

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

it('ends the turn with an error, asking no model and saving nothing, when storage cannot read the file or uploads are off', function (): void {
    asFileModel();
    $missing = new Attachment(['filepath' => 'workspaces/w/chat/gone.pdf', 'filename' => 'gone.pdf', 'mime_type' => 'application/pdf']);
    $unreadable = attachmentTurn($missing);
    config(['chatvia.ai.chat_files.enabled' => false]);
    $replies = new RecordingReplyRecorder;

    $off = iterator_to_array(attachmentSource(replies: $replies)->events(TestChatContext::make(), [['role' => 'user', 'content' => 'Hello']], collect([attachmentStored()])), false);

    expect(array_map(static fn (StreamEvent $e): EventType => $e->type, $unreadable))->toBe([EventType::Start, EventType::Error, EventType::Done])
        ->and($unreadable[1]->data['message'])->toBe('Something went wrong while answering from the file.')
        ->and(array_map(static fn (StreamEvent $e): EventType => $e->type, $off))->toBe([EventType::Start, EventType::Error, EventType::Done])
        ->and($off[1]->data['message'])->toBe('Chatting with files is not available.')
        ->and($replies->saved)->toBe([])
        ->and($this->sent)->toBe([]);
});
