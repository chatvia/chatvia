<?php

namespace App\Http\Requests\Settings\Functions;

use App\Auth\Models\User;
use Closure;
use Domain\Agents\Data\Functions\CreateAgentFunctionData;
use Illuminate\Foundation\Http\FormRequest;

class CreateAgentFunctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
                    if ($this->user()->functions()->where('name', $value)->exists()) {
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
            // Flat form payload; `toData()` reshapes these into the
            // structured `auth_config` array per auth_type.
            'auth_header_name' => ['nullable', 'required_if:auth_type,header', 'string', 'max:128'],
            'auth_value' => ['nullable', 'required_if:auth_type,bearer,header,hmac', 'string', 'max:8192'],
        ];
    }

    public function toData(): CreateAgentFunctionData
    {
        /** @var User $user */
        $user = $this->user();

        /** @var array<string, mixed> $schema */
        $schema = json_decode($this->string('parameters_schema')->toString(), true);

        $authType = $this->string('auth_type', 'none')->toString();

        $authConfig = match ($authType) {
            'bearer' => ['token' => (string) $this->input('auth_value')],
            'header' => [
                'name' => (string) $this->input('auth_header_name'),
                'value' => (string) $this->input('auth_value'),
            ],
            'hmac' => ['secret' => (string) $this->input('auth_value')],
            default => null,
        };

        return new CreateAgentFunctionData(
            user: $user,
            name: $this->string('name')->toString(),
            description: $this->string('description')->toString(),
            parameters_schema: $schema,
            webhook_url: $this->string('webhook_url')->toString(),
            webhook_method: $this->string('webhook_method', 'POST')->toString(),
            auth_type: $authType,
            auth_config: $authConfig,
        );
    }
}
