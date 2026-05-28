<?php

namespace App\Http\Controllers\Api\V1\Functions;

use App\Auth\Models\User;
use App\Http\Resources\Api\V1\FunctionData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Account-level webhook-backed functions. Define a function once (e.g.
 * `get_order_status`) and attach it to any number of your agents via
 * `POST /api/v1/agents/{id}/functions`. Each function declares a name and
 * JSON-schema parameters; when an LLM invokes it, Chatvia fires a webhook
 * to the configured URL with the parameters as the body and forwards the
 * response back to the model.
 *
 * Auth credentials (`auth_config`, `webhook_headers`, `webhook_secret`) are
 * encrypted at rest and never returned in responses.
 */
#[Group(name: 'Functions', weight: 60)]
class FunctionController
{
    /**
     * List functions
     *
     * @return array{data: list<FunctionData>}
     */
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $functions = $user->functions()->latest()->get();

        return ['data' => $functions->map(fn (AgentFunction $function): FunctionData => FunctionData::fromModel($function))->all()];
    }

    /**
     * Create function
     *
     * `parameters_schema` is the JSON-schema shown to the LLM. Use the
     * standard JSON-schema vocabulary: `{type: "object", properties: {...}, required: [...]}`.
     *
     * @return array{data: FunctionData}
     */
    public function store(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'description' => ['required', 'string', 'max:1024'],
            'parameters_schema' => ['required', 'array'],
            'webhook_url' => ['required', 'url', 'max:2048'],
            'webhook_method' => ['sometimes', 'in:POST,GET,PATCH,DELETE'],
            'webhook_headers' => ['sometimes', 'nullable', 'array'],
            'auth_type' => ['sometimes', 'in:none,bearer,header,hmac'],
            'auth_header_name' => ['nullable', 'required_if:auth_type,header', 'string', 'max:128'],
            'auth_value' => ['nullable', 'required_if:auth_type,bearer,header,hmac', 'string', 'max:8192'],
            'timeout_ms' => ['sometimes', 'integer', 'min:1000', 'max:30000'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        if ($user->functions()->where('name', $validated['name'])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A function with this name already exists on your account.',
            ]);
        }

        $authType = $validated['auth_type'] ?? 'none';

        $function = $user->functions()->create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'parameters_schema' => $validated['parameters_schema'],
            'webhook_url' => $validated['webhook_url'],
            'webhook_method' => $validated['webhook_method'] ?? 'POST',
            'webhook_headers' => $validated['webhook_headers'] ?? null,
            'auth_type' => $authType,
            'auth_config' => $this->buildAuthConfig($authType, $validated),
            'timeout_ms' => $validated['timeout_ms'] ?? 10000,
            'enabled' => $validated['enabled'] ?? true,
        ]);

        return ['data' => FunctionData::fromModel($function)];
    }

    /**
     * Get function
     *
     * @return array{data: FunctionData}
     */
    public function show(Request $request, AgentFunction $function): array
    {
        $this->assertOwnership($request, $function);

        return ['data' => FunctionData::fromModel($function)];
    }

    /**
     * Update function
     *
     * Changes propagate instantly to every agent the function is attached to.
     *
     * @return array{data: FunctionData}
     */
    public function update(Request $request, AgentFunction $function): array
    {
        $this->assertOwnership($request, $function);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'description' => ['sometimes', 'string', 'max:1024'],
            'parameters_schema' => ['sometimes', 'array'],
            'webhook_url' => ['sometimes', 'url', 'max:2048'],
            'webhook_method' => ['sometimes', 'in:POST,GET,PATCH,DELETE'],
            'webhook_headers' => ['sometimes', 'nullable', 'array'],
            'auth_type' => ['sometimes', 'in:none,bearer,header,hmac'],
            'auth_header_name' => ['nullable', 'required_if:auth_type,header', 'string', 'max:128'],
            'auth_value' => ['nullable', 'required_if:auth_type,bearer,header,hmac', 'string', 'max:8192'],
            'timeout_ms' => ['sometimes', 'integer', 'min:1000', 'max:30000'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        // If auth_type changes, rebuild auth_config from the flat fields;
        // otherwise leave the existing config alone.
        if (array_key_exists('auth_type', $validated)) {
            $validated['auth_config'] = $this->buildAuthConfig($validated['auth_type'], $validated);
        }
        unset($validated['auth_header_name'], $validated['auth_value']);

        $function->update($validated);

        return ['data' => FunctionData::fromModel($function->refresh())];
    }

    /**
     * Delete function
     *
     * Also removes it from every agent it's attached to.
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

    /**
     * Shape the flat `auth_value` (+ `auth_header_name` when type=header)
     * inputs into the typed auth_config array stored on the model.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>|null
     */
    private function buildAuthConfig(string $authType, array $input): ?array
    {
        return match ($authType) {
            'bearer' => ['token' => (string) ($input['auth_value'] ?? '')],
            'header' => [
                'name' => (string) ($input['auth_header_name'] ?? ''),
                'value' => (string) ($input['auth_value'] ?? ''),
            ],
            'hmac' => ['secret' => (string) ($input['auth_value'] ?? '')],
            default => null,
        };
    }
}
