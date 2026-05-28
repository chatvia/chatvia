<?php

namespace App\Http\Requests\Settings;

use App\Auth\Models\User;
use Closure;
use Domain\Agents\Data\UpdateAgentFunctionData;
use Domain\Agents\Models\AgentFunction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = $this->user()->functions()
                        ->where('name', $value)
                        ->where('external_id', '!=', $this->route('external_id'))
                        ->exists();
                    if ($exists) {
                        $fail('A function with this name already exists on your account.');
                    }
                },
            ],
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
            'webhook_url' => ['required', 'url', 'max:2048'],
            'webhook_method' => ['sometimes', 'in:POST,GET,PATCH,DELETE'],
            'auth_type' => ['sometimes', 'in:none,bearer,header,hmac'],
            'auth_header_name' => ['nullable', 'required_if:auth_type,header', 'string', 'max:128'],
            'auth_value' => ['nullable', 'string', 'max:8192'],
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
            name: $this->string('name')->toString(),
            description: $this->string('description')->toString(),
            parameters_schema: $schema,
            webhook_url: $this->string('webhook_url')->toString(),
            webhook_method: $this->string('webhook_method', 'POST')->toString(),
            auth_type: $this->string('auth_type', 'none')->toString(),
            auth_header_name: filled($authHeaderName) ? (string) $authHeaderName : null,
            auth_value: filled($authValue) ? (string) $authValue : null,
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
