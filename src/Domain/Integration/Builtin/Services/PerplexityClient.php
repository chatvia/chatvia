<?php

namespace Domain\Integration\Builtin\Services;

use Domain\Integration\Builtin\Data\Perplexity\PerplexityAgentRequestData;
use Domain\Integration\Builtin\Data\Perplexity\PerplexityAnswerData;
use Domain\Integration\Builtin\Data\Perplexity\PerplexitySandboxExecutionData;
use Domain\Integration\Builtin\Data\Perplexity\PerplexitySandboxRunData;
use Domain\Integration\Builtin\Data\Perplexity\PerplexitySonarRequestData;
use Domain\Integration\Builtin\Exceptions\PerplexityException;
use Illuminate\Support\Facades\Http;

/**
 * Perplexity's two answer APIs, called with the workspace's own API key (the
 * Perplexity connector is bring-your-own-key):
 *
 *  - Sonar (chat completions) for reasoned answers — `ask` and `research`.
 *  - The Agent API (/v1/agent) with one built-in tool enabled — web search,
 *    page fetch, finance, people and the code sandbox.
 *
 * Both return a {@see PerplexityAnswerData}: the answer text plus each cited
 * URL once, in the order first cited.
 */
class PerplexityClient
{
    private const string SANDBOX_RESULTS = 'sandbox_results';

    /**
     * @throws PerplexityException
     */
    public function sonar(string $apiKey, PerplexitySonarRequestData $request): PerplexityAnswerData
    {
        $messages = [];
        if ($request->systemPrompt !== null) {
            $messages[] = ['role' => 'system', 'content' => $request->systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $request->query];

        $body = $this->post($apiKey, '/chat/completions', [
            'model' => $request->model,
            'messages' => $messages,
        ], $request->timeout);

        $answer = $this->sonarAnswer($body);

        if ($answer->answer === '') {
            throw PerplexityException::noAnswer();
        }

        return $answer;
    }

    /**
     * @throws PerplexityException
     */
    public function agent(string $apiKey, PerplexityAgentRequestData $request): PerplexityAnswerData
    {
        $payload = [
            'model' => (string) config('services.perplexity.agent_model', 'perplexity/sonar'),
            'input' => $request->input,
            'tools' => [['type' => $request->toolType]],
        ];
        if ($request->instructions !== null) {
            $payload['instructions'] = $request->instructions;
        }

        $output = $this->outputItems($this->post($apiKey, '/v1/agent', $payload, $request->timeout));
        $answer = $this->agentAnswer($output);

        if ($answer->answer === '' && $answer->citations === []) {
            throw PerplexityException::noAnswer();
        }

        return $answer;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     *
     * @throws PerplexityException
     */
    private function post(string $apiKey, string $path, array $payload, int $timeout): ?array
    {
        $baseUrl = rtrim((string) config('services.perplexity.base_url', 'https://api.perplexity.ai'), '/');

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->acceptJson()
            ->post($baseUrl.$path, $payload);

        if ($response->failed()) {
            throw PerplexityException::failed($response->status());
        }

        $body = $response->json();

        return is_array($body) ? $body : null;
    }

    /**
     * Newer Sonar responses carry `search_results` (title + url); older ones
     * only a flat `citations` list of URLs.
     *
     * @param  array<string, mixed>|null  $body
     */
    private function sonarAnswer(?array $body): PerplexityAnswerData
    {
        if ($body === null) {
            return new PerplexityAnswerData('', []);
        }

        $content = (string) ($body['choices'][0]['message']['content'] ?? '');
        // Reasoning models (sonar-reasoning*) wrap their chain-of-thought in
        // <think>…</think> before the final answer — strip it so the agent
        // only sees the answer. Harmless for non-reasoning models.
        $answer = trim((string) preg_replace('/<think>.*?<\/think>/s', '', $content));

        $citations = new CitationList;
        foreach ($body['search_results'] ?? [] as $result) {
            $citations->add((string) ($result['url'] ?? ''), (string) ($result['title'] ?? ''));
        }

        if ($citations->isEmpty()) {
            foreach ($body['citations'] ?? [] as $url) {
                $citations->add((string) $url);
            }
        }

        return new PerplexityAnswerData($answer, $citations->all());
    }

    /**
     * The Agent API answers with an `output` list: tool-result items followed
     * by the assistant message. Hits sit under `results` (search, people,
     * finance) or `contents` (fetch_url); finance results also list plain-URL
     * `sources`.
     *
     * @param  list<array<string, mixed>>  $output
     */
    private function agentAnswer(array $output): PerplexityAnswerData
    {
        $answer = '';
        $citations = new CitationList;

        foreach ($output as $item) {
            if (($item['type'] ?? null) === 'message' && ($item['role'] ?? null) === 'assistant') {
                foreach ((array) ($item['content'] ?? []) as $chunk) {
                    if (is_array($chunk) && isset($chunk['text'])) {
                        $answer .= (string) $chunk['text'];
                    }
                }

                continue;
            }

            foreach (['results', 'contents'] as $key) {
                foreach ((array) ($item[$key] ?? []) as $result) {
                    if (! is_array($result)) {
                        continue;
                    }
                    $citations->add((string) ($result['url'] ?? ''), (string) ($result['title'] ?? ''));
                    foreach ((array) ($result['sources'] ?? []) as $source) {
                        if (is_string($source)) {
                            $citations->add($source);
                        }
                    }
                }
            }
        }

        return new PerplexityAnswerData(trim($answer), $citations->all(), $this->sandboxRun($output));
    }

    /**
     * What the sandbox executed, from the first `sandbox_results` item.
     *
     * @param  list<array<string, mixed>>  $output
     */
    private function sandboxRun(array $output): ?PerplexitySandboxRunData
    {
        $item = array_find($output, static fn (array $item): bool => ($item['type'] ?? null) === self::SANDBOX_RESULTS);

        if ($item === null) {
            return null;
        }

        $executions = [];
        foreach ((array) ($item['results'] ?? []) as $run) {
            if (! is_array($run)) {
                continue;
            }
            $executions[] = new PerplexitySandboxExecutionData(
                stdout: (string) ($run['stdout'] ?? ''),
                stderr: (string) ($run['stderr'] ?? ''),
                exitCode: $run['exit_code'] ?? null,
            );
        }

        return new PerplexitySandboxRunData(
            language: (string) ($item['language'] ?? ''),
            code: (string) ($item['code'] ?? ''),
            status: (string) ($item['status'] ?? ''),
            executions: $executions,
        );
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return list<array<string, mixed>>
     */
    private function outputItems(?array $body): array
    {
        $output = $body['output'] ?? [];

        return is_array($output) ? array_values(array_filter($output, 'is_array')) : [];
    }
}
