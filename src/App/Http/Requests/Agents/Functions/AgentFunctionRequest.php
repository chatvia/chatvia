<?php

namespace App\Http\Requests\Agents\Functions;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Domain\Agents\Rules\SafeWebhookUrl;
use Domain\Chat\Services\Tooling\FunctionSchemaValidator;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentFunctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agent = $this->route('agent');

        return $agent instanceof Agent
            && $this->user()?->can('update', $agent) === true;
    }

    public function rules(): array
    {
        $agent = $this->route('agent');
        $existing = $this->route('function');
        $existingId = $existing instanceof AgentFunction ? $existing->id : null;

        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:64',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique('agent_functions', 'name')
                    ->where(fn ($q) => $q->where('agent_id', $agent?->id))
                    ->ignore($existingId),
            ],
            'description' => ['required', 'string', 'min:1', 'max:1024'],
            'parameters_schema' => ['required', 'array', $this->validJsonSchema()],
            'webhook_url' => ['required', 'string', 'max:2048', new SafeWebhookUrl],
            'webhook_method' => ['required', Rule::in(['POST', 'GET'])],
            'webhook_headers' => ['nullable', 'array', 'max:20'],
            'webhook_headers.*.key' => ['required_with:webhook_headers.*.value', 'string', 'max:128'],
            'webhook_headers.*.value' => ['required_with:webhook_headers.*.key', 'string', 'max:1024'],
            'auth_type' => ['required', Rule::in(['none', 'bearer', 'header', 'hmac'])],
            'auth_value' => ['nullable', 'string', 'max:2048', 'required_unless:auth_type,none,hmac'],
            'timeout_ms' => ['required', 'integer', 'min:500', 'max:30000'],
            'max_response_bytes' => ['required', 'integer', 'min:1024', 'max:524288'],
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'The function name must start with a letter and contain only letters, numbers, and underscores.',
        ];
    }

    /**
     * Delegate shape validation to FunctionSchemaValidator so the rule stays
     * thin and the same logic is reusable from other call sites (e.g. the
     * LLM tool loop in Slice 3).
     */
    private function validJsonSchema(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $validator = app(FunctionSchemaValidator::class);
            foreach ($validator->validateShape($value) as $message) {
                $fail($message);
            }
        };
    }
}
