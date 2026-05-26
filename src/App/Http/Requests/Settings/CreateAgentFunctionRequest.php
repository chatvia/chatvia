<?php

namespace App\Http\Requests\Settings;

use App\Auth\Models\User;
use Closure;
use Domain\Agents\Data\CreateAgentFunctionData;
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
            'webhook_method' => ['sometimes', 'in:POST,GET'],
            'auth_type' => ['sometimes', 'in:none,bearer,header,hmac'],
            'auth_value' => ['sometimes', 'nullable', 'string', 'max:8192'],
        ];
    }

    public function toData(): CreateAgentFunctionData
    {
        /** @var User $user */
        $user = $this->user();

        /** @var array<string, mixed> $schema */
        $schema = json_decode($this->string('parameters_schema')->toString(), true);

        return new CreateAgentFunctionData(
            user: $user,
            name: $this->string('name')->toString(),
            description: $this->string('description')->toString(),
            parameters_schema: $schema,
            webhook_url: $this->string('webhook_url')->toString(),
            webhook_method: $this->string('webhook_method', 'POST')->toString(),
            auth_type: $this->string('auth_type', 'none')->toString(),
            auth_value: $this->input('auth_value'),
        );
    }
}
