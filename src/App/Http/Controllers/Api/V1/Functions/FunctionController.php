<?php

namespace App\Http\Controllers\Api\V1\Functions;

use App\Auth\Models\User;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Functions
 *
 * Account-level webhook-backed functions. Define a function once (e.g.
 * `get_order_status`) and attach it to any number of your agents via
 * `POST /api/v1/agents/{id}/functions`. Each function declares a name and
 * JSON-schema parameters; when an LLM invokes it, Chatvia fires a webhook
 * to the configured URL with the parameters as the body and forwards the
 * response back to the model.
 *
 * Auth credentials (`auth_value`, `webhook_headers`, `webhook_secret`) are
 * encrypted at rest and never returned in responses.
 *
 * Master token required (ability `agents:manage`).
 */
class FunctionController
{
    /**
     * List functions
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $functions = $user->functions()->latest()->get();

        return response()->json([
            'data' => $functions->map(fn (AgentFunction $f): array => $this->transform($f))->all(),
        ]);
    }

    /**
     * Create function
     *
     * `parameters_schema` is the JSON-schema shown to the LLM. Use the
     * standard JSON-schema vocabulary: `{type: "object", properties: {...}, required: [...]}`.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'description' => ['required', 'string', 'max:1024'],
            'parameters_schema' => ['required', 'array'],
            'webhook_url' => ['required', 'url', 'max:2048'],
            'webhook_method' => ['sometimes', 'in:POST,GET'],
            'webhook_headers' => ['sometimes', 'nullable', 'array'],
            'auth_type' => ['sometimes', 'in:none,bearer,header,hmac'],
            'auth_value' => ['sometimes', 'nullable', 'string', 'max:8192'],
            'timeout_ms' => ['sometimes', 'integer', 'min:1000', 'max:30000'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        if ($user->functions()->where('name', $validated['name'])->exists()) {
            return response()->json([
                'message' => 'A function with this name already exists on your account.',
                'errors' => ['name' => ['Function name must be unique per account.']],
            ], 422);
        }

        $function = $user->functions()->create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'parameters_schema' => $validated['parameters_schema'],
            'webhook_url' => $validated['webhook_url'],
            'webhook_method' => $validated['webhook_method'] ?? 'POST',
            'webhook_headers' => $validated['webhook_headers'] ?? null,
            'auth_type' => $validated['auth_type'] ?? 'none',
            'auth_value' => $validated['auth_value'] ?? null,
            'timeout_ms' => $validated['timeout_ms'] ?? 10000,
            'enabled' => $validated['enabled'] ?? true,
        ]);

        return response()->json(['function' => $this->transform($function)], 201);
    }

    /**
     * Get function
     */
    public function show(Request $request, AgentFunction $function): JsonResponse
    {
        $this->assertOwnership($request, $function);

        return response()->json(['function' => $this->transform($function)]);
    }

    /**
     * Update function
     *
     * Changes propagate instantly to every agent the function is attached to.
     */
    public function update(Request $request, AgentFunction $function): JsonResponse
    {
        $this->assertOwnership($request, $function);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'description' => ['sometimes', 'string', 'max:1024'],
            'parameters_schema' => ['sometimes', 'array'],
            'webhook_url' => ['sometimes', 'url', 'max:2048'],
            'webhook_method' => ['sometimes', 'in:POST,GET'],
            'webhook_headers' => ['sometimes', 'nullable', 'array'],
            'auth_type' => ['sometimes', 'in:none,bearer,header,hmac'],
            'auth_value' => ['sometimes', 'nullable', 'string', 'max:8192'],
            'timeout_ms' => ['sometimes', 'integer', 'min:1000', 'max:30000'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $function->update($validated);

        return response()->json(['function' => $this->transform($function->refresh())]);
    }

    /**
     * Delete function
     *
     * Also detaches it from every agent (CASCADE on the pivot).
     */
    public function destroy(Request $request, AgentFunction $function): JsonResponse
    {
        $this->assertOwnership($request, $function);

        $function->delete();

        return response()->json(null, 204);
    }

    private function assertOwnership(Request $request, AgentFunction $function): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($function->user_id !== $user->id, 404);
    }

    private function transform(AgentFunction $f): array
    {
        return [
            'id' => $f->external_id,
            'name' => $f->name,
            'description' => $f->description,
            'parameters_schema' => $f->parameters_schema,
            'webhook' => [
                'url' => $f->webhook_url,
                'method' => $f->webhook_method,
                'has_headers' => $f->webhook_headers !== null && $f->webhook_headers !== [],
            ],
            'auth_type' => $f->auth_type,
            'has_auth_value' => $f->auth_value !== null,
            'timeout_ms' => $f->timeout_ms,
            'enabled' => $f->enabled,
            'last_invoked_at' => $f->last_invoked_at?->toIso8601String(),
            'invocation_count' => $f->invocation_count,
            'created_at' => $f->created_at?->toIso8601String(),
        ];
    }
}
