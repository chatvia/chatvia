<?php

namespace Domain\Analytics\Services\Chat;

use App\Identity\Workspace\Models\Workspace;
use Domain\Analytics\Data\Chat\AnalyticsChatRequestData;
use Domain\Analytics\Data\Chat\AnalyticsChatTurnData;
use Domain\Conversation\Data\Tooling\EmittedToolCallData;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Sse\ToolCallPhase;
use Domain\Conversation\Services\Streaming\ToolCallDeltas;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Services\Routing\BuiltinToolRouter;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Services\AiCallTags;
use Domain\Llm\Services\LlmProviders;
use Domain\Llm\Services\TaskModels;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The owner-facing "Ask your data" turn: a self-contained streaming tool loop
 * over the internal analytics and charts MCP servers, with NO agent, NO
 * conversation, and nothing persisted. Each turn streams the model, runs any
 * analytics/chart tool calls scoped to the workspace, feeds the results back,
 * and repeats until the model gives a final answer.
 *
 * It yields {@see StreamEvent}s; the HTTP layer turns them into an SSE
 * response. It is separate from the production chat engine on purpose: the
 * read-only analytics tools need none of ToolLoopRunner's governance,
 * approval, routing or persistence machinery.
 */
class AnalyticsChatRunner
{
    private const int MAX_HISTORY = 20;

    public function __construct(
        private readonly LlmProviders $providers,
        private readonly TaskModels $models,
        private readonly AnalyticsToolset $toolset,
        private readonly AnalyticsToolDispatcher $dispatcher,
        private readonly BuiltinToolRouter $router,
        private readonly AiCallTags $tags,
    ) {}

    /**
     * The turn as a stream of events: start, text deltas, tool start/end
     * pairs, then done (or error + done).
     *
     * @return Generator<int, StreamEvent>
     */
    public function events(Workspace $workspace, AnalyticsChatRequestData $data): Generator
    {
        $assistantId = (string) Str::uuid();

        yield StreamEvent::start($assistantId, [
            'assistant_message_id' => $assistantId,
            'user_message_id' => $data->userMessageId,
            'replayed' => false,
        ]);

        try {
            yield from $this->toolLoop($workspace, $data, $assistantId);

            yield StreamEvent::done($assistantId, 'complete');
        } catch (Throwable $e) {
            Log::error('Analytics chat stream crashed', ['message' => $e->getMessage()]);
            report($e);

            yield StreamEvent::error($assistantId, ErrorCode::Internal, __('Something went wrong while answering. Please try again.'));
            yield StreamEvent::done($assistantId, 'error');
        }
    }

    /**
     * Stream the model, run the tools it asks for, and repeat until it answers
     * without a tool call. After the last allowed round it answers once more
     * with tools off, so the reply never ends on tool results alone.
     *
     * @return Generator<int, StreamEvent>
     */
    private function toolLoop(Workspace $workspace, AnalyticsChatRequestData $data, string $assistantId): Generator
    {
        ['definitions' => $tools, 'routes' => $routes] = $this->toolsFor($data->message);
        $messages = $this->buildMessages($workspace, $data);
        $model = $this->models->model(LlmTask::Chat);
        $provider = $this->providers->for($model);
        $maxIterations = max(1, (int) config('chatvia.agent.tools.max_iterations', 5));

        for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
            $assistantText = '';
            $pending = [];

            $response = $this->tags->during(AiCallTag::AnalyticsAssistant, fn () => $provider->stream($model->name, $messages, $model->requestSettings([
                'temperature' => 0.2,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ])));

            yield from $this->consume($response, $assistantId, $assistantText, $pending);

            $calls = array_map(EmittedToolCallData::fromStreamBuffer(...), array_values($pending));

            if ($calls === []) {
                return;
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $assistantText,
                'tool_calls' => array_map(static fn (EmittedToolCallData $call): array => $call->toOpenAiToolCall(), $calls),
            ];

            foreach ($calls as $call) {
                yield from $this->runToolCall($workspace, $routes, $messages, $assistantId, $call);
            }
        }

        // Out of rounds while the model still wanted tools. Any call it makes
        // anyway is left in $pending and never run.
        $assistantText = '';
        $pending = [];
        $response = $this->tags->during(AiCallTag::AnalyticsAssistant, fn () => $provider->stream($model->name, $messages, $model->requestSettings([
            'temperature' => 0.2,
            'tools' => $tools,
            'tool_choice' => 'none',
        ])));

        yield from $this->consume($response, $assistantId, $assistantText, $pending);
    }

    /**
     * Only the built-in tools relevant to this question, from the embedded
     * registry. Falls back to the full reflection-built toolset before the
     * registry is synced (or when routing finds nothing).
     *
     * @return array{definitions: array<int, array<string, mixed>>, routes: array<string, string>}
     */
    private function toolsFor(string $question): array
    {
        $routed = $this->router->route($question, [McpServerCategory::Analytics, McpServerCategory::Builtin]);

        return $routed['definitions'] === [] ? $this->toolset->build() : $routed;
    }

    /**
     * Parse the provider's OpenAI-compatible SSE stream: relay text deltas and
     * accumulate any tool-call fragments (which arrive split across chunks).
     *
     * @param  array<int, array{id: string, name: string, arguments: string}>  $pending
     * @return Generator<int, StreamEvent>
     */
    private function consume(ResponseInterface $response, string $assistantId, string &$assistantText, array &$pending): Generator
    {
        $body = $response->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));

                if ($payload === '[DONE]') {
                    return;
                }

                $chunk = json_decode($payload, true);

                if (is_array($chunk)) {
                    yield from $this->applyChunk($chunk, $assistantId, $assistantText, $pending);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $chunk
     * @param  array<int, array{id: string, name: string, arguments: string}>  $pending
     * @return Generator<int, StreamEvent>
     */
    private function applyChunk(array $chunk, string $assistantId, string &$assistantText, array &$pending): Generator
    {
        $delta = $chunk['choices'][0]['delta'] ?? [];

        // Not filled(): a whitespace-only delta (" ", "\n") is real output.
        if (isset($delta['content']) && is_string($delta['content']) && $delta['content'] !== '') {
            $assistantText .= $delta['content'];

            yield StreamEvent::delta($assistantId, $delta['content']);
        }

        foreach ((array) ($delta['tool_calls'] ?? []) as $fragment) {
            if (is_array($fragment)) {
                $pending = ToolCallDeltas::merge($pending, $fragment);
            }
        }
    }

    /**
     * Run one tool call, emit its start/end events (charts ride along as
     * `artifacts` on the end event), and append the `role: 'tool'` result so the
     * next iteration can use it.
     *
     * @param  array<string, string>  $routes
     * @param  array<int, array<string, mixed>>  $messages
     * @return Generator<int, StreamEvent>
     */
    private function runToolCall(Workspace $workspace, array $routes, array &$messages, string $assistantId, EmittedToolCallData $call): Generator
    {
        $url = $routes[$call->name] ?? null;

        yield StreamEvent::tool(
            assistantMessageId: $assistantId,
            phase: ToolCallPhase::Start,
            toolCallId: $call->id,
            name: $call->name,
            args: $call->arguments,
            kind: 'mcp',
            label: $this->toolLabel($url),
        );

        if ($url === null) {
            $unknown = 'Tool error: unknown tool.';
            $messages[] = ['role' => 'tool', 'tool_call_id' => $call->id, 'content' => $unknown];

            yield StreamEvent::tool($assistantId, ToolCallPhase::End, $call->id, name: $call->name, result: $unknown);

            return;
        }

        $arguments = json_decode($call->arguments !== '' ? $call->arguments : '{}', true);
        $result = $this->dispatcher->dispatch($workspace, $call->name, $url, is_array($arguments) ? $arguments : []);

        $messages[] = ['role' => 'tool', 'tool_call_id' => $call->id, 'content' => $result->text];

        yield StreamEvent::tool(
            assistantMessageId: $assistantId,
            phase: ToolCallPhase::End,
            toolCallId: $call->id,
            name: $call->name,
            result: $result->text,
            artifacts: $result->artifacts !== [] ? $result->artifacts : null,
        );
    }

    /**
     * The badge for a tool call: the `tool_label` of the built-in server the
     * call routes to (config/mcp.php), "Analytics" when none matches.
     */
    private function toolLabel(?string $url): string
    {
        $slug = $url === null ? null : basename((string) parse_url($url, PHP_URL_PATH));

        foreach ((array) config('mcp.builtin', []) as $server) {
            if (($server['slug'] ?? null) === $slug) {
                return (string) ($server['tool_label'] ?? 'Analytics');
            }
        }

        return 'Analytics';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(Workspace $workspace, AnalyticsChatRequestData $data): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($workspace)]];

        foreach (array_slice($data->history, -self::MAX_HISTORY) as $turn) {
            if ($this->isReplayable($turn)) {
                $messages[] = ['role' => $turn->role, 'content' => $turn->content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $data->message];

        return $messages;
    }

    private function isReplayable(AnalyticsChatTurnData $turn): bool
    {
        return in_array($turn->role, ['user', 'assistant'], true) && $turn->content !== '';
    }

    private function systemPrompt(Workspace $workspace): string
    {
        return trim(prompt('analytics/chat-system', [
            'workspace' => $workspace->name,
            'today' => now()->toFormattedDateString(),
        ]));
    }
}
