<?php

namespace Domain\Integration\Function\Services;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\FunctionInvoked;
use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Tool\Contracts\ToolDispatcherInterface;
use Domain\Integration\Tool\Data\ToolCallData;
use Domain\Integration\Tool\Data\ToolDispatchResult;
use Domain\Integration\Tool\Dispatch\ToolArgumentValidator;
use Domain\Integration\Tool\Dispatch\ToolCallerIdentity;
use Domain\Integration\Tool\Exceptions\InvalidToolArgumentsException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;
use Support\Http\WebhookSignature;
use Throwable;

/**
 * Runs a webhook function (FunctionTool): finds the function attached to the
 * agent, checks the URL is safe, calls it with the function's HTTP method and
 * auth (bearer, custom header, or an HMAC signature), and returns the response
 * text to the model. Failures come back as tool errors, never exceptions.
 *
 * The model's arguments are checked against the function's parameters first
 * ({@see ToolArgumentValidator}). A webhook URL often holds a secret (a hook
 * path, an `api_key` query), so the model only ever reads a fixed sentence
 * about a failed call, and the stored `last_error` and the log keep the
 * error with every URL cut down to its host.
 *
 * The `X-Chatvia-*` header names are a public contract that customers' webhook
 * receivers check, so they keep this name even when the product is rebranded.
 */
class HttpToolDispatcher implements ToolDispatcherInterface
{
    public const string TIMESTAMP_HEADER = 'X-Chatvia-Timestamp';

    public const string SIGNATURE_HEADER = 'X-Chatvia-Signature';

    /** What the model reads when the webhook cannot be reached. */
    public const string UNREACHABLE_RESULT = 'the webhook could not be reached. Try again later.';

    /** What the model reads when the call failed in any other way. */
    public const string FAILED_RESULT = 'the webhook call failed unexpectedly. Try again later.';

    /** Longest error kept on the function row and in the log. */
    private const int MAX_ERROR_LENGTH = 500;

    public function __construct(
        private readonly ToolArgumentValidator $validator = new ToolArgumentValidator,
    ) {}

    public function dispatch(Agent $agent, ToolCallData $call): ToolDispatchResult
    {
        $name = $call->name->value;
        $function = $this->findAttachedFunction($agent, $name);

        if (! $function instanceof AgentFunction) {
            return $this->error("Function \"{$name}\" is not attached to this agent or is disabled.");
        }

        $args = $this->decodeArgs($call->arguments);
        if ($args === null) {
            return $this->error('Invalid arguments JSON from the model.');
        }

        try {
            $args = $this->validator->validated($function->parameters_schema?->getArrayCopy() ?? [], $args);
        } catch (InvalidToolArgumentsException $e) {
            return $this->error($e->getMessage());
        }

        // Dispatch-time SSRF guard: re-validate the (DNS-resolved) host right
        // before connecting, connect to the address that was checked and
        // disable redirect following, so a webhook that passed save-time
        // validation cannot point/redirect at internal infrastructure (cloud
        // metadata, RFC1918, loopback).
        try {
            $clientOptions = SafeUrl::pinnedClientOptions($function->webhook_url);
        } catch (UnsafeUrlException $e) {
            $this->record($function, $agent, error: 'blocked unsafe webhook url');

            return $this->error('the webhook address of this function is not allowed.');
        }

        $method = $function->webhook_method;
        $payload = $this->encodePayload($method, $args);
        $headers = $this->headers($function, $agent, $call);

        if ($function->auth_type === FunctionAuthType::Hmac) {
            $secret = (string) (($function->auth_config ?? [])['secret'] ?? '');

            // Never send an unsigned request to an endpoint that expects one.
            if ($secret === '') {
                $this->record($function, $agent, error: 'missing hmac signing secret');

                return $this->error('the HMAC signing secret for this function is missing.');
            }

            // Stamped last so a customer webhook header can't shadow them.
            $headers = array_merge($headers, $this->signatureHeaders($secret, $payload));
        }

        return $this->send($function, $agent, $method, $payload, $headers, $clientOptions);
    }

    /**
     * The enabled function with this name, only when it is attached to the agent.
     */
    private function findAttachedFunction(Agent $agent, string $name): ?AgentFunction
    {
        return AgentFunction::query()
            ->where('workspace_id', $agent->workspace_id)
            ->where('internal_name', $name)
            ->where('is_enabled', true)
            ->whereHas('agents', fn (Builder $agents): Builder => $agents->whereKey($agent->id))
            ->first();
    }

    /**
     * Build the exact bytes we send, so an HMAC signature covers what the
     * receiver gets: GET carries the arguments as a query string, every other
     * method as a JSON body.
     *
     * @param  array<string, mixed>  $args
     */
    private function encodePayload(FunctionWebhookMethod $method, array $args): string
    {
        return $method === FunctionWebhookMethod::Get
            ? Arr::query($args)
            : (string) json_encode($args);
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $clientOptions  From {@see SafeUrl::pinnedClientOptions()}.
     */
    private function send(AgentFunction $function, Agent $agent, FunctionWebhookMethod $method, string $payload, array $headers, array $clientOptions): ToolDispatchResult
    {
        $start = microtime(true);

        try {
            $request = Http::timeout(max(1, (int) ceil($function->timeout_ms / 1000)))
                ->withOptions($clientOptions)
                ->withHeaders($headers);

            $response = $method === FunctionWebhookMethod::Get
                ? $request->send($method->value, $function->webhook_url, ['query' => $payload])
                : $request->withBody($payload, 'application/json')->send($method->value, $function->webhook_url);

            $latency = (int) round((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                $error = sprintf('webhook returned %d', $response->status());
                $this->record($function, $agent, error: $error);

                return $this->error($error);
            }

            $truncated = Str::substr($response->body(), 0, $function->max_response_bytes);

            $this->record($function, $agent);
            Log::info('Tool dispatched', [
                'agent' => $agent->id,
                'function' => $function->internal_name,
                'latency_ms' => $latency,
            ]);

            return ToolDispatchResult::text($truncated);
        } catch (Throwable $e) {
            $message = $this->withoutUrls($e->getMessage());
            $this->record($function, $agent, error: $message);
            Log::warning('Tool dispatch failed', [
                'agent' => $agent->id,
                'function' => $function->internal_name,
                'exception' => $e::class,
                'error' => $message,
            ]);

            return $this->error($e instanceof ConnectionException ? self::UNREACHABLE_RESULT : self::FAILED_RESULT);
        }
    }

    /**
     * The error text with every URL cut down to its scheme and host: an HTTP
     * client error quotes the full request URL, whose path or query may be
     * the webhook's secret.
     */
    private function withoutUrls(string $message): string
    {
        return Str::of($message)
            ->replaceMatches('~\b(https?://)(?:[^\s/?#@"\'<>]*@)?([^\s/?#"\'<>]+)[^\s"\'<>]*~i', '$1$2/[redacted]')
            ->limit(self::MAX_ERROR_LENGTH)
            ->toString();
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

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, string>
     */
    private function headers(AgentFunction $function, Agent $agent, ToolCallData $call): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'Chatvia/Tools',
        ];

        foreach ((array) ($function->webhook_headers ?? []) as $h) {
            if (isset($h['key'], $h['value'])) {
                $headers[$h['key']] = $h['value'];
            }
        }

        // auth_config shape varies per auth_type:
        //   bearer → ['token' => '...']
        //   header → ['name' => '...', 'value' => '...']
        //   hmac   → ['secret' => '...'] (signs the request, see signatureHeaders())
        $config = $function->auth_config ?? [];

        if ($function->auth_type === FunctionAuthType::Bearer && ! empty($config['token'])) {
            $headers['Authorization'] = 'Bearer '.$config['token'];
        }
        if ($function->auth_type === FunctionAuthType::Header && ! empty($config['name']) && isset($config['value'])) {
            $headers[(string) $config['name']] = (string) $config['value'];
        }

        // Identity headers are stamped last so a customer's webhook_headers
        // can't shadow them.
        return array_merge($headers, ToolCallerIdentity::headers($agent, $call->conversationExternalId, $call->chatterExternalId));
    }

    /**
     * Signature headers for `hmac` auth. The receiver recomputes
     * HMAC-SHA256 over "{timestamp}.{payload}" with the shared secret — the
     * payload being the exact raw JSON body, or for GET the raw query string —
     * compares it in constant time, and rejects old timestamps so a captured
     * request cannot be replayed later.
     *
     * @return array<string, string>
     */
    private function signatureHeaders(string $secret, string $payload): array
    {
        $timestamp = (string) now()->getTimestamp();

        return [
            self::TIMESTAMP_HEADER => $timestamp,
            self::SIGNATURE_HEADER => 'sha256='.WebhookSignature::sign($timestamp.'.'.$payload, $secret),
        ];
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
        // reached for it. A raw `+ 1` keeps the increment atomic when several
        // turns call the same function at once.
        DB::table('agent_function')
            ->where('agent_id', $agent->id)
            ->where('function_id', $function->id)
            ->update([
                'invocation_count' => DB::raw('invocation_count + 1'),
                'last_invoked_at' => $now,
            ]);

        // Durable per-day rollup so usage insights survive function deletion.
        FunctionInvoked::dispatch(
            $agent->id,
            (int) $agent->workspace_id,
            $function->id,
            $function->display_name ?: $function->internal_name,
            $function->description,
            $now,
        );
    }

    private function error(string $message): ToolDispatchResult
    {
        return ToolDispatchResult::error('Tool error: '.$message);
    }
}
