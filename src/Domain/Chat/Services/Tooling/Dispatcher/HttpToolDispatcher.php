<?php

namespace Domain\Chat\Services\Tooling\Dispatcher;

use Domain\Agent\Models\Agent;
use Domain\Chat\Services\Tooling\Registry\ToolCallerIdentity;
use Domain\Function\Models\AgentFunction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpToolDispatcher implements ToolDispatcherInterface
{
    public function dispatch(Agent $agent, string $name, string $arguments, ?string $conversationExternalId = null): ToolDispatchResult
    {
        $function = AgentFunction::query()
            ->where('user_id', $agent->user_id)
            ->where('name', $name)
            ->where('is_enabled', true)
            ->whereExists(function ($q) use ($agent): void {
                $q->from('agent_function')
                    ->whereColumn('agent_function.function_id', 'functions.id')
                    ->where('agent_function.agent_id', $agent->id);
            })
            ->first();

        if (! $function) {
            return $this->error("Function \"{$name}\" is not attached to this agent or is disabled.");
        }

        $args = $this->decodeArgs($arguments);
        if ($args === null) {
            return $this->error('Invalid arguments JSON from the model.');
        }

        $start = microtime(true);

        try {
            $request = Http::timeout(max(1, (int) ceil($function->timeout_ms / 1000)))
                ->withHeaders($this->headers($function, $agent, $conversationExternalId));

            $response = $function->webhook_method === 'GET'
                ? $request->get($function->webhook_url, $args)
                : $request->post($function->webhook_url, $args);

            $latency = (int) round((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                $error = sprintf('webhook returned %d', $response->status());
                $this->record($function, $agent, error: $error);

                return $this->error($error);
            }

            $body = (string) $response->body();
            $truncated = mb_substr($body, 0, $function->max_response_bytes);

            $this->record($function, $agent);
            Log::info('Tool dispatched', [
                'agent' => $agent->id,
                'function' => $name,
                'latency_ms' => $latency,
            ]);

            return ToolDispatchResult::text($truncated);
        } catch (Throwable $e) {
            $message = $e->getMessage();
            $this->record($function, $agent, error: $message);
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
    private function headers(AgentFunction $function, Agent $agent, ?string $conversationExternalId = null): array
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

        // auth_config shape varies per auth_type:
        //   bearer → ['token' => '...']
        //   header → ['name' => '...', 'value' => '...']
        //   hmac   → ['secret' => '...'] (used by request body signing, not headers)
        $config = $function->auth_config ?? [];

        if ($function->auth_type === 'bearer' && ! empty($config['token'])) {
            $headers['Authorization'] = 'Bearer '.$config['token'];
        }
        if ($function->auth_type === 'header' && ! empty($config['name']) && isset($config['value'])) {
            $headers[(string) $config['name']] = (string) $config['value'];
        }

        // Identity headers are stamped last so a customer's webhook_headers
        // can't shadow them.
        return array_merge($headers, ToolCallerIdentity::headers($agent, $conversationExternalId));
    }

    private function record(AgentFunction $function, Agent $agent, ?string $error = null): void
    {
        $now = now();

        $function->forceFill([
            'last_invoked_at' => $now,
            'last_error' => $error,
            'invocation_count' => $function->invocation_count + 1,
        ])->save();

        // Per-agent counter on the pivot — drives the Sources analytics page.
        // The global `functions.invocation_count` above sums across every
        // agent in the account; this one tells us how often *this* agent
        // reached for it.
        DB::table('agent_function')
            ->where('agent_id', $agent->id)
            ->where('function_id', $function->id)
            ->update([
                'invocation_count' => DB::raw('invocation_count + 1'),
                'last_invoked_at' => $now,
            ]);
    }

    private function error(string $message): ToolDispatchResult
    {
        return ToolDispatchResult::text('Tool error: '.$message);
    }
}
