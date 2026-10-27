<?php

namespace Domain\Conversation\Services\Streaming\Sources;

use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\ChatTurnStreamer;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Conversation\Services\Turn\CitationMarkers;
use Domain\Guardrail\Services\GuardrailInspector;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Llm\Contracts\ChatCompletionProvider;
use Domain\Llm\Enums\FileInput;
use Domain\Llm\Exceptions\LlmProviderUnavailableException;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\ChatFileModel;
use Domain\Llm\Services\LlmProviders;
use Generator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToReadFile;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Streams a turn of a conversation with attached files: every file is added
 * to the latest user turn, a PDF as a `document_url` part and an image as an
 * `image_url` part (each provider client writes them in its own form). The
 * file goes inside the request as a data: URL rather than a link to this
 * site: a model handed a link it cannot open (a local or private install)
 * answers about the link instead of the file.
 *
 * Activated by {@see ChatTurnStreamer} whenever the conversation has any
 * attachment. The agent's own model answers when it reads PDF files and
 * images; otherwise the platform's model for chat files does
 * ({@see ChatFileModel}), so an agent on a text-only model can still take
 * files. The model reads the whole file directly, with no separate OCR +
 * retrieval step. Providers keep no state, so the file travels with every
 * turn of the conversation, next to the usual history. With uploads turned
 * off, or a file of a type no chat file may be, the turn ends with a clear
 * error.
 *
 * Tool calling IS supported and the answer passes the same output guardrails:
 * the envelope, tool loop and finish step are shared with LlmSource through
 * {@see ToolLoopSource}, so MCP / webhook tools still fire (and pause for
 * approval) inside these conversations. A resumed turn stays on the model for
 * chat files so the files (already in the snapshot's latest user turn) keep
 * flowing. The snapshot holds the files themselves; a chat allows one file
 * (`chatvia.agent.files.upload`), so it stays small.
 */
class AttachmentSource extends ToolLoopSource
{
    /** Longest provider error body written to the log. */
    private const int MAX_LOGGED_BODY_LENGTH = 2000;

    public function __construct(
        private readonly ChatFileModel $fileModel,
        private readonly LlmProviders $providers,
        AssistantReplyRecorder $replyRecorder,
        RequestDedupeStore $dedupe,
        ToolSelector $toolSelector,
        ToolLoopRunner $runner,
        GuardrailInspector $guardrails,
        GuardrailRecorder $guardrailRecorder,
        CitationMarkers $citations,
    ) {
        parent::__construct($replyRecorder, $dedupe, $toolSelector, $runner, $guardrails, $guardrailRecorder, $citations);
    }

    /**
     * @param  array<int, array<string, mixed>>  $llmMessages  Conversation history (last entry is the new user turn).
     * @param  Collection<int, Attachment>  $attachments  Every file attached to this conversation; each is added to the latest user turn.
     * @return Generator<int, StreamEvent>
     */
    public function events(ChatContext $context, array $llmMessages, Collection $attachments): Generator
    {
        if (! $this->fileModel->isAvailable()) {
            yield from $this->refuse($context, __('Chatting with files is not available.'));

            return;
        }

        if ($attachments->contains(fn (Attachment $attachment): bool => ! FileInput::forMimeType($attachment->mime_type) instanceof FileInput)) {
            yield from $this->refuse($context, __('The AI model cannot read this type of file. Start a new chat to attach another file.'));

            return;
        }

        $parts = $attachments->map(fn (Attachment $attachment): ?array => $this->part($attachment));

        // Answering without the file would mislead, so a file storage cannot read ends the turn.
        if ($parts->contains(null)) {
            yield from $this->refuse($context, $this->turnFailedMessage());

            return;
        }

        yield from $this->streamTurn($context, $this->withParts($llmMessages, $parts->values()->all()));
    }

    protected function provider(ChatContext $context): ChatCompletionProvider
    {
        return $this->providers->for($this->model($context));
    }

    protected function modelName(ChatContext $context): string
    {
        return $this->model($context)->name;
    }

    /**
     * The model that answers, with what it takes (the agent's settings are
     * fitted to it): the agent's own model when it reads PDF files and
     * images, else the platform's model for chat files. events() has checked
     * uploads are on; a resumed turn whose model went away since fails like
     * any unreachable model.
     */
    protected function model(ChatContext $context): Llm
    {
        $agentModel = $context->requestData->message->model;

        if ($this->fileModel->readsFiles($agentModel)) {
            return $agentModel;
        }

        return $this->fileModel->find() ?? throw new LlmProviderUnavailableException(__('Chatting with files is not available.'));
    }

    protected function logLabel(): string
    {
        return 'File chat';
    }

    protected function upstreamUnavailableMessage(): string
    {
        return __('The selected model could not be reached. Please try again.');
    }

    protected function turnFailedMessage(): string
    {
        return __('Something went wrong while answering from the file.');
    }

    /**
     * The provider's actual complaint, so the log shows more than "400 Bad Request".
     *
     * @return array<string, mixed>
     */
    protected function upstreamLogContext(Throwable $e): array
    {
        return ['response_body' => $this->upstreamBody($e)];
    }

    /**
     * End the turn with an error before any answer is streamed.
     *
     * @return Generator<int, StreamEvent>
     */
    private function refuse(ChatContext $context, string $message): Generator
    {
        yield StreamEvent::start($context->assistantMessageId, [
            'assistant_message_id' => $context->assistantMessageId,
            'user_message_id' => $context->userMessageId,
            'request_id' => $context->requestData->requestId,
            'language' => $context->queryLanguage,
            'replayed' => false,
        ]);
        yield StreamEvent::error($context->assistantMessageId, ErrorCode::Internal, $message);
        yield StreamEvent::done($context->assistantMessageId, 'error');
    }

    /**
     * The stored file as a message part with its bytes in a data: URL: a
     * `document_url` for a PDF, an `image_url` for an image. Null when
     * storage cannot read it.
     *
     * @return array<string, mixed>|null
     */
    private function part(Attachment $attachment): ?array
    {
        try {
            $contents = Storage::disk(Attachment::DISK)->get($attachment->filepath);
        } catch (UnableToReadFile $e) {
            report($e);

            return null;
        }

        if ($contents === null) {
            return null;
        }

        $url = "data:{$attachment->mime_type};base64,".base64_encode($contents);

        return FileInput::forMimeType($attachment->mime_type) === FileInput::Image
            ? ['type' => 'image_url', 'image_url' => ['url' => $url]]
            : ['type' => 'document_url', 'document_url' => $url, 'document_name' => $attachment->filename];
    }

    /**
     * Rewrite the last user turn so its `content` is a list of typed parts
     * with every attached file appended. The provider reads files fresh on
     * every request (no server-side state), so the files travel on each new
     * turn rather than only the first.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<int, array<string, mixed>>
     */
    private function withParts(array $messages, array $parts): array
    {
        $lastUserIndex = array_key_last(array_filter(
            $messages,
            static fn (array $message): bool => ($message['role'] ?? '') === MessageRole::User->value,
        ));

        if ($parts === [] || $lastUserIndex === null) {
            return $messages;
        }

        $text = $messages[$lastUserIndex]['content'] ?? '';

        $messages[$lastUserIndex] = [
            'role' => MessageRole::User->value,
            'content' => [
                ['type' => 'text', 'text' => is_array($text) ? '' : (string) $text],
                ...$parts,
            ],
        ];

        return $messages;
    }

    /**
     * Pull the response body off a Guzzle exception so the log shows
     * the provider's actual complaint instead of just "400 Bad Request".
     */
    private function upstreamBody(Throwable $e): ?string
    {
        if (! $e instanceof RequestException) {
            return null;
        }
        $response = $e->getResponse();
        if (! $response instanceof ResponseInterface) {
            return null;
        }

        $body = (string) $response->getBody();

        return Str::length($body) > self::MAX_LOGGED_BODY_LENGTH
            ? Str::substr($body, 0, self::MAX_LOGGED_BODY_LENGTH).'…[truncated]'
            : $body;
    }
}
