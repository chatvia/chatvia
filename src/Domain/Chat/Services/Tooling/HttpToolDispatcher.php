<?php

namespace Domain\Chat\Services\Tooling;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Domain\Agents\Rules\SafeWebhookUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpToolDispatcher implements ToolDispatcherInterface
{
    public function dispatch(Agent $agent, string $name, string $arguments): string
    {
        $function = AgentFunction::query()
            ->where('agent_id', $agent->id)
            ->where('name', $name)
            ->where('enabled', true)
            ->first();

        if (! $function) {
            return $this->error("Function \"{$name}\" is not defined or disabled for this agent.");
        }

        $args = $this->decodeArgs($arguments);
        if ($args === null) {
            return $this->error('Invalid arguments JSON from the model.');
        }

        $start = microtime(true);

        try {
            $request = Http::timeout(max(1, (int) ceil($function->timeout_ms / 1000)))
                ->withHeaders($this->headers($function));

            $response = $function->webhook_method === 'GET'
                ? $request->get($function->webhook_url, $args)
                : $request->post($function->webhook_url, $args);

            $latency = (int) round((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                $error = sprintf('webhook returned %d', $response->status());
                $this->record($function, error: $error);

                return $this->error($error);
            }

            $body = (string) $response->body();
            $truncated = mb_substr($body, 0, $function->max_response_bytes);

            $this->record($function);
            Log::info('Tool dispatched', [
                'agent' => $agent->id,
                'function' => $name,
                'latency_ms' => $latency,
            ]);

            return $truncated;
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $this->record($function, error: $message);
            Log::warning('Tool dispatch failed', [
                'agent' => $agent->id,
                'function' => $name,
                'error' => $message,
            ]);

            return $this->error($message);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeArgs(string $arguments): ?array
    {
        if ($arguments === '') {
            return [];
        }

        $decoded = json_decode($arguments, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array<string, string>
     */
    private function headers(AgentFunction $function): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'ChatVia/Tools',
        ];

        foreach ((array) ($function->webhook_headers ?? []) as $h) {
            if (isset($h['key'], $h['value'])) {
                $headers[$h['key']] = $h['value'];
            }
        }

        if ($function->auth_type === 'bearer' && $function->auth_value) {
            $headers['Authorization'] = 'Bearer '.$function->auth_value;
        }
        if ($function->auth_type === 'header' && $function->auth_value) {
            // For "header" auth we expect the user to have configured the
            // header name in their custom headers — the value here is
            // appended raw.
            $headers['X-Auth'] = $function->auth_value;
        }

        return $headers;
    }

    private function record(AgentFunction $function, ?string $error = null): void
    {
        $function->forceFill([
            'last_invoked_at' => now(),
            'last_error' => $error,
            'invocation_count' => $function->invocation_count + 1,
        ])->save();
    }

    private function error(string $message): string
    {
        return 'Tool error: '.$message;
    }
}
