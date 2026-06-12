<?php

namespace Domain\Analytics\Services\Chat;

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Data\Chat\AnalyticsChatRequestData;
use Domain\Conversation\Services\Streaming\Sse\ErrorCode;
use Domain\Conversation\Services\Streaming\Sse\SseStreamer;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Services\Routing\BuiltinToolRouter;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\Llm;
use Generator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The owner-facing "Ask your data" turn: a self-contained streaming tool-loop
 * that reuses the SSE envelope + the internal analytics/charts MCP servers but
 * has NO agent, NO conversation, and persists NOTHING. Each call streams the
 * model, dispatches any analytics/chart tool calls scoped to the workspace,
 * feeds the results back, and repeats until the model gives a final answer.
 *
 * Decoupled from the production chat engine on purpose (see the plan): the
 * read-only analytics tools need none of ToolLoopRunner's governance, approval,
 * routing, or persistence machinery.
 */
class AnalyticsChatRunner
{
    private const MAX_HISTORY = 20;

    public function __construct(
        private readonly LlmProviderInterface $provider,
        private readonly SseStreamer $sse,
        private readonly AnalyticsToolset $toolset,
        private readonly AnalyticsToolDispatcher $dispatcher,
        private readonly BuiltinToolRouter $router,
    ) {}

    public function stream(User $workspace, AnalyticsChatRequestData $data): StreamedResponse
    {
        return $this->sse->respond($this->events($workspace, $data));
    }

    /**
     * The turn as a stream of {@see StreamEvent}s. Public so tests can iterate
     * it directly without going through the SSE/output-buffer layer.
     *
     * @return Generator<int, StreamEvent>
     */
    public function events(User $workspace, AnalyticsChatRequestData $data): Generator
    {
        $assistantId = (string) Str::uuid();

        yield StreamEvent::start($assistantId, [
            'assistant_message_id' => $assistantId,
            'user_message_id' => $data->userMessageId,
            'replayed' => false,
        ]);

        try {
            // Route to only the relevant built-in tools for this turn from the
            // cached + embedded registry. Falls back to the reflection-built
            // full toolset before the registry is synced (or if routing is empty).
            $routed = $this->router->route($data->message, [McpServerCategory::Analytics, McpServerCategory::Builtin]);
            ['definitions' => $tools, 'routes' => $routes] = $routed['definitions'] === []
                ? $this->toolset->build()
                : $routed;
            $messages = $this->buildMessages($workspace, $data);
            $modelName = $this->resolveModel();
            $maxIterations = (int) config('chatvia.agent.tools.max_iterations', 5);

            for ($iteration = 0; $iteration < $maxIterations; $iteration++) {
                $assistantText = '';
                $pending = [];

                $response = $this->provider->stream($modelName, $messages, [
                    'temperature' => 0.2,
                    'tools' => $tools,
                    'tool_choice' => 'auto',
                ]);

                yield from $this->consume($response, $assistantId, $assistantText, $pending);

                $calls = array_values($pending);
                if ($calls === []) {
                    break;
                }

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $assistantText,
                    'tool_calls' => array_map(static fn (array $tc): array => [
                        'id' => $tc['id'],
                        'type' => 'function',
                        'function' => ['name' => $tc['name'], 'arguments' => $tc['arguments']],
                    ], $calls),
                ];

                foreach ($calls as $tc) {
                    yield from $this->runToolCall($workspace, $routes, $messages, $assistantId, $tc);
                }
            }

            yield StreamEvent::done($assistantId, 'complete');
        } catch (Throwable $e) {
            Log::error('Analytics chat stream crashed', ['message' => $e->getMessage()]);
            report($e);

            yield StreamEvent::error($assistantId, ErrorCode::Internal, 'Something went wrong while answering. Please try again.');
            yield StreamEvent::done($assistantId, 'error');
        }
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
                if (! is_array($chunk)) {
                    continue;
                }

                $delta = $chunk['choices'][0]['delta'] ?? [];

                if (isset($delta['content']) && is_string($delta['content']) && $delta['content'] !== '') {
                    $assistantText .= $delta['content'];
                    yield StreamEvent::delta($assistantId, $delta['content']);
                }

                if (isset($delta['tool_calls']) && is_array($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $fragment) {
                        if (is_array($fragment)) {
                            $this->applyToolCallDelta($pending, $fragment);
                        }
                    }
                }
            }
        }
    }

    /**
     * Accumulate a streamed tool-call fragment by its index — id + name land on
     * the first fragment, the JSON arguments arrive as appended string pieces.
     * Mirrors ChatContext::applyToolCallDelta.
     *
     * @param  array<int, array{id: string, name: string, arguments: string}>  $pending
     * @param  array<string, mixed>  $fragment
     */
    private function applyToolCallDelta(array &$pending, array $fragment): void
    {
        $index = (int) ($fragment['index'] ?? 0);
        $current = $pending[$index] ?? ['id' => '', 'name' => '', 'arguments' => ''];

        if (isset($fragment['id']) && is_string($fragment['id'])) {
            $current['id'] = $fragment['id'];
        }

        $function = $fragment['function'] ?? null;
        if (is_array($function)) {
            if (isset($function['name']) && is_string($function['name'])) {
                $current['name'] = $function['name'];
            }
            if (isset($function['arguments']) && is_string($function['arguments'])) {
                $current['arguments'] .= $function['arguments'];
            }
        }

        $pending[$index] = $current;
    }

    /**
     * Dispatch one tool call, emit its start/end events (charts ride along as
     * `artifacts` on the end event), and append the `role: 'tool'` result so the
     * next iteration can use it.
     *
     * @param  array<string, string>  $routes
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array{id: string, name: string, arguments: string}  $tc
     * @return Generator<int, StreamEvent>
     */
    private function runToolCall(User $workspace, array $routes, array &$messages, string $assistantId, array $tc): Generator
    {
        $name = $tc['name'];
        $url = $routes[$name] ?? null;
        $isChart = $url !== null && str_contains($url, '/mcp/charts');

        yield StreamEvent::tool(
            assistantMessageId: $assistantId,
            phase: 'start',
            toolCallId: $tc['id'],
            name: $name,
            args: $tc['arguments'],
            kind: 'mcp',
            label: $isChart ? 'Chart' : 'Analytics',
        );

        if ($url === null) {
            $messages[] = ['role' => 'tool', 'tool_call_id' => $tc['id'], 'content' => 'Tool error: unknown tool.'];
            yield StreamEvent::tool($assistantId, 'end', $tc['id'], name: $name, result: 'Tool error: unknown tool.');

            return;
        }

        $arguments = json_decode($tc['arguments'] !== '' ? $tc['arguments'] : '{}', true);
        $result = $this->dispatcher->dispatch($workspace, $name, $url, is_array($arguments) ? $arguments : []);

        $messages[] = ['role' => 'tool', 'tool_call_id' => $tc['id'], 'content' => $result->text];

        yield StreamEvent::tool(
            assistantMessageId: $assistantId,
            phase: 'end',
            toolCallId: $tc['id'],
            name: $name,
            result: $result->text,
            artifacts: $result->artifacts !== [] ? $result->artifacts : null,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(User $workspace, AnalyticsChatRequestData $data): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($workspace)]];

        foreach (array_slice($data->history, -self::MAX_HISTORY) as $turn) {
            $role = $turn['role'] ?? null;
            $content = $turn['content'] ?? null;
            if (in_array($role, ['user', 'assistant'], true) && is_string($content) && $content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $data->message];

        return $messages;
    }

    private function systemPrompt(User $workspace): string
    {
        $today = now()->toFormattedDateString();

        return <<<TXT
        You are the analytics assistant for the Chatvia workspace "{$workspace->name}". Answer the owner's questions about their own account using the account-analytics tools, which return exact numbers scoped to this workspace.

        Guidelines:
        - Only ever present REAL numbers from the account-analytics tools — never invent, estimate, or fabricate figures, including sample data for a chart.
        - You exist to answer questions about THIS account's data. If the user asks to "plot"/"chart" something with no real metric behind it and gives no values (e.g. "plot a bar from 1–10"), do NOT make up numbers — ask what they'd like to chart, or offer a real metric (messages, channels, top users, …) and chart that.
        - The default period is the current billing cycle. For a "total", "overall", "all-time" or "so far" question, pass `period: all_time`. Only pass other periods when the user names one.
        - Lead with the number, then a short plain-English sentence. Keep answers concise.
        - The analytics tools already render the right chart automatically when the answer is a breakdown or trend — you do NOT need to call a separate chart tool. Only call a charts tool if the user explicitly asks for a different chart type, and only with figures you fetched from a tool or exact values the user supplied.
        - If a tool returns zeros, say there's no data for that period rather than guessing.

        Today is {$today}.
        TXT;
    }

    private function resolveModel(): string
    {
        $default = (string) config('chatvia.agent.default_model');
        $llm = Llm::query()->where('external_name', $default)->first();

        return $llm?->internal_name ?? $default;
    }
}
