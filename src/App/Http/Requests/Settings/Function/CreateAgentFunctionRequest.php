<?php

namespace App\Http\Requests\Settings\Function;

use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Integration\Function\Data\FunctionDefinitionData;
use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Rules\ValidParametersSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

/**
 * The Settings → Functions "new function" form. The parameters schema arrives
 * as JSON text from the editor; credentials arrive flat and are stored as the
 * typed `auth_config` by the Action.
 */
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
            'internal_name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique('functions', 'internal_name')
                    ->where('user_id', $this->user()->workspace()->id),
            ],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1024'],
            'parameters_schema' => ['required', 'string', new ValidParametersSchema],
            'webhook_url' => ['required', 'url', 'max:2048', new SafeWebhookUrl],
            'webhook_method' => ['sometimes', Rule::enum(FunctionWebhookMethod::class)],
            'auth_type' => ['sometimes', Rule::enum(FunctionAuthType::class)],
            'auth_header_name' => ['nullable', 'required_if:auth_type,header', 'string', 'max:128'],
            'auth_value' => ['nullable', 'required_if:auth_type,bearer,header,hmac', 'string', 'max:8192'],
            'requires_approval' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): FunctionDefinitionData
    {
        /** @var array<string, mixed> $schema */
        $schema = json_decode($this->string('parameters_schema')->toString(), true);

        return new FunctionDefinitionData(
            internal_name: $this->string('internal_name')->toString(),
            display_name: $this->string('display_name')->toString() ?: null,
            description: $this->string('description')->toString(),
            parameters_schema: $schema,
            webhook_url: $this->string('webhook_url')->toString(),
            webhook_method: $this->enum('webhook_method', FunctionWebhookMethod::class) ?? FunctionWebhookMethod::Post,
            auth_type: $this->enum('auth_type', FunctionAuthType::class) ?? FunctionAuthType::None,
            auth_header_name: $this->input('auth_header_name'),
            auth_value: $this->input('auth_value'),
            requires_approval: $this->has('requires_approval') ? $this->boolean('requires_approval') : new Optional,
        );
    }
}
