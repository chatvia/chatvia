<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Exceptions\ClientDisconnected;
use Domain\Conversation\Services\Streaming\Requests\RequestDedupeStore;
use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sources\PdfSource;
use Domain\Conversation\Services\Streaming\Sources\ToolLoopSource;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Tooling\Routing\ToolSelector;
use Domain\Conversation\Services\Tooling\ToolLoopRunner;
use Domain\Conversation\Services\Turn\AssistantReplyRecorder;
use Domain\Guardrail\Data\GuardResultData;
use Domain\Guardrail\Data\GuardVerdictData;
use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Domain\Guardrail\Services\GuardrailRecorder;
use Domain\Guardrail\Services\GuardrailService;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\Llm;
use Domain\Llm\Services\DocumentLlm;
use Domain\Llm\Services\LlmProviders;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Every source that runs the tool loop shares one finish step, so the output
 * guardrails (PII redaction / toxicity replacement) run before ANY answer is
 * saved or replayed — PDF chats included, and also the partial answer saved
 * after the visitor disconnects. The runner and the guard are faked; the
 * assertions are on what the source saves and streams.
 */
function tlsGuard(): GuardrailService
{
    return new class extends GuardrailService
    {
        public function __construct() {}

        public function inspectOutput(Agent $agent, string $text): GuardResultData
        {
            if (! str_contains($text, 'a@b.com')) {
                return GuardResultData::clean($text);
            }

            return new GuardResultData(
                text: str_replace('a@b.com', '[EMAIL]', $text),
                verdicts: [new GuardVerdictData(GuardType::Pii, GuardStage::Output, GuardOutcome::Redacted)],
            );
        }
    };
}

function tlsRecorder(): GuardrailRecorder
{
    return new class extends GuardrailRecorder
    {
        /** @var array<int, string> */
        public array $samples = [];

        public function record(ChatContext $context, string $sample, GuardResultData $result): void
        {
            $this->samples[] = $sample;
        }
    };
}

/**
 * A reply recorder whose only job here is to remember the text it was asked
 * to save.
 */
function tlsReplyRecorder(): AssistantReplyRecorder
{
    return new class extends AssistantReplyRecorder
    {
        public ArrayObject $saved;

        public function __construct()
        {
            $this->saved = new ArrayObject;
        }

        public function recordCompletedReply(ChatContext $context): void
        {
            $this->saved->append($context->streamedMessage);
        }
    };
}

function tlsToolSelector(): ToolSelector
{
    return new class extends ToolSelector
    {
        public function __construct() {}

        public function selectForTurn(Agent $agent, string $message, ?string $conversationId): array
        {
            return [];
        }
    };
}

function tlsRunner(Closure $scripted): ToolLoopRunner
{
    return new class($scripted) extends ToolLoopRunner
    {
        public function __construct(private readonly Closure $scripted) {}

        public function run(
            ChatContext $context,
            LlmProviderInterface $provider,
            string $modelName,
            array &$llmMessages,
            array $toolDefinitions,
            array $extraSettings = [],
            int $startIteration = 0,
            bool $captureToolCallsOnly = false,
        ): Generator {
            return yield from ($this->scripted)($context);
        }
    };
}

function tlsContext(): ChatContext
{
    $agent = Agent::factory()->create();
    $llm = Llm::factory()->create();

    $requestData = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: $llm, message: 'Hello'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
        requestId: 'req_'.Str::random(12),
    );

    return new ChatContext(
        requestData: $requestData,
        payload: UserTurnData::from([
            'external_session_id' => $requestData->externalSessionId,
            'external_conversation_id' => $requestData->conversationId,
            'external_message_id' => (string) Str::uuid(),
            'model' => $llm->external_name,
            'message' => 'Hello',
            'sent_at' => now(),
        ]),
        llmMessages: [['role' => 'user', 'content' => 'Hello']],
        userMessageId: (string) Str::uuid(),
        assistantMessageId: (string) Str::uuid(),
    );
}

/**
 * Build the named source with the shared fakes and return its event stream.
 *
 * @return array{0: Generator<int, StreamEvent>, 1: AssistantReplyRecorder, 2: GuardrailRecorder}
 */
function tlsStream(string $source, ChatContext $context, ToolLoopRunner $runner): array
{
    $replyRecorder = tlsReplyRecorder();
    $recorder = tlsRecorder();

    $shared = [$replyRecorder, new RequestDedupeStore, tlsToolSelector(), $runner, tlsGuard(), $recorder];

    $events = match ($source) {
        'pdf' => new PdfSource(new DocumentLlm(LlmProviderEnum::MISTRAL), ...$shared)->events($context, [['role' => 'user', 'content' => 'Hello']], collect()),
        default => new LlmSource(app(LlmProviders::class), ...$shared)->events($context, [['role' => 'user', 'content' => 'Hello']]),
    };

    return [$events, $replyRecorder, $recorder];
}

beforeEach(function (): void {
    Cache::flush();
    Log::spy();
});

dataset('tool loop sources', ['llm', 'pdf']);

it('is the shared base of every tool-loop source', function (): void {
    expect(is_subclass_of(LlmSource::class, ToolLoopSource::class))->toBeTrue()
        ->and(is_subclass_of(PdfSource::class, ToolLoopSource::class))->toBeTrue();
});

it('redacts the answer with the output guardrails before saving and replaying it', function (string $source): void {
    $context = tlsContext();
    $runner = tlsRunner(function (ChatContext $ctx) {
        $ctx->appendStreamedMessage('Mail me at a@b.com');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Mail me at a@b.com');

        return false;
    });

    [$stream, $replyRecorder, $recorder] = tlsStream($source, $context, $runner);
    $events = iterator_to_array($stream, preserve_keys: false);

    $replace = collect($events)->first(fn (StreamEvent $e): bool => $e->type === EventType::Replace);

    expect($replace)->not->toBeNull()
        ->and($replace->data['text'])->toBe('Mail me at [EMAIL]')
        ->and($replyRecorder->saved->getArrayCopy())->toBe(['Mail me at [EMAIL]'])
        ->and($recorder->samples)->toBe(['Mail me at [EMAIL]'])
        ->and(app(RequestDedupeStore::class)->find($context->requestData->requestId)->content)->toBe('Mail me at [EMAIL]')
        ->and(end($events)->data['reason'])->toBe('complete');
})->with('tool loop sources');

it('redacts the partial answer it saves after the client disconnects', function (string $source): void {
    $context = tlsContext();
    $runner = tlsRunner(function (ChatContext $ctx) {
        $ctx->appendStreamedMessage('Mail me at a@b.com');
        yield StreamEvent::delta($ctx->assistantMessageId, 'Mail me at');
        throw new ClientDisconnected;
    });

    [$stream, $replyRecorder] = tlsStream($source, $context, $runner);
    iterator_to_array($stream, preserve_keys: false);

    expect($replyRecorder->saved->getArrayCopy())->toBe(['Mail me at [EMAIL]'])
        ->and(app(RequestDedupeStore::class)->find($context->requestData->requestId)->content)->toBe('Mail me at [EMAIL]');
})->with('tool loop sources');

it('ends a PDF turn with a clear error, saving nothing, when no LLM can read PDF documents', function (): void {
    $context = tlsContext();
    $replyRecorder = tlsReplyRecorder();
    $runner = tlsRunner(function (): Generator {
        throw new RuntimeException('The tool loop must not run without a document LLM.');
    });

    $source = new PdfSource(new DocumentLlm(null), $replyRecorder, new RequestDedupeStore, tlsToolSelector(), $runner, tlsGuard(), tlsRecorder());
    $events = iterator_to_array($source->events($context, [['role' => 'user', 'content' => 'Hello']], collect()), false);

    expect(array_map(static fn (StreamEvent $e): EventType => $e->type, $events))->toBe([EventType::Start, EventType::Error, EventType::Done])
        ->and($events[1]->data['message'])->toBe('Chatting with PDF files is not available.')
        ->and($events[1]->data['message'])->not->toContain('Mistral')
        ->and($replyRecorder->saved->getArrayCopy())->toBe([]);
});
