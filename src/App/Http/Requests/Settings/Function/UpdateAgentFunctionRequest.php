<?php

namespace App\Http\Requests\Settings\Function;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Integration\Function\Data\UpdateAgentFunctionData;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Foundation\Http\FormRequest;
use Domain\Agent\Rules\SafeWebhookUrl;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Domain\Integration\Function\Enums\FunctionAuthType;

class UpdateAgentFunctionRequest extends FormRequest
{
    private ?AgentFunction $function = null;

    public function authorize(): bool
    {
        return $this->function() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'internal_name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique('functions', 'internal_name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($this->route('external_id'), 'external_id'),
            ],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1024'],
            'parameters_schema' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $decoded = json_decode((string) $value, true);
                    if (! is_array($decoded)) {
                        $fail('parameters_schema must be valid JSON describing the parameters.');
                    }
                },
            ],
            'webhook_url' => ['required', 'url', 'max:2048', new SafeWebhookUrl],
            'webhook_method' => ['sometimes', 'in:POST,GET,PATCH,DELETE'],
            'auth_type' => ['sometimes', Rule::enum(FunctionAuthType::class)],
            'auth_header_name' => ['nullable', 'required_if:auth_type,header', 'string', 'max:128'],
            'auth_value' => ['nullable', 'string', 'max:8192'],
            'requires_approval' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A blank `auth_value` means "keep the stored secret". This check lives in an
     * after-hook (not a field rule) because `nullable` short-circuits field rules
     * on empty input — and empty input is exactly the case we need to police.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->string('auth_type', 'none')->toString();
            if (! in_array($type, ['bearer', 'header', 'hmac'], true)) {
                return;
            }
            if (filled($this->input('auth_value'))) {
                return;
            }
            $function = $this->function();
            $canKeep = $function !== null && $function->auth_type === $type && $function->auth_config !== null;
            if (! $canKeep) {
                $validator->errors()->add('auth_value', 'A credential value is required for this auth type.');
            }
        });
    }

    public function toData(): UpdateAgentFunctionData
    {
        /** @var array<string, mixed> $schema */
        $schema = json_decode($this->string('parameters_schema')->toString(), true);

        $authValue = $this->input('auth_value');
        $authHeaderName = $this->input('auth_header_name');

        return new UpdateAgentFunctionData(
            internal_name: $this->string('internal_name')->toString(),
            display_name: $this->string('display_name')->toString() ?: null,
            description: $this->string('description')->toString(),
            parameters_schema: $schema,
            webhook_url: $this->string('webhook_url')->toString(),
            webhook_method: $this->string('webhook_method', 'POST')->toString(),
            auth_type: $this->string('auth_type', 'none')->toString(),
            auth_header_name: filled($authHeaderName) ? (string) $authHeaderName : null,
            auth_value: filled($authValue) ? (string) $authValue : null,
            requires_approval: $this->has('requires_approval') ? $this->boolean('requires_approval') : null,
        );
    }

    private function function(): ?AgentFunction
    {
        if ($this->function !== null) {
            return $this->function;
        }

        /** @var User $user */
        $user = $this->user();

        return $this->function = $user->functions()
            ->where('external_id', $this->route('external_id'))
            ->first();
    }
}
