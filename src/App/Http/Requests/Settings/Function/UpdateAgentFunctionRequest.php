<?php

namespace App\Http\Requests\Settings\Function;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Integration\Function\Data\FunctionDefinitionData;
use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Rules\ValidParametersSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Optional;

/**
 * The Settings → Functions edit form for one function (`{external_id}` in the
 * workspace). It replaces the whole definition, except that a blank
 * credential keeps the stored one when the auth type is unchanged.
 */
class UpdateAgentFunctionRequest extends FormRequest
{
    private ?AgentFunction $function = null;

    public function authorize(): bool
    {
        return $this->findFunction() !== null;
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
                    ->where('user_id', $this->user()->workspace()->id)
                    ->ignore($this->route('external_id'), 'external_id'),
            ],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1024'],
            'parameters_schema' => [
                'required',
                'string',
                new ValidParametersSchema($this->findFunction()?->parameters_schema?->getArrayCopy()),
            ],
            'webhook_url' => ['required', 'url', 'max:2048', new SafeWebhookUrl],
            'webhook_method' => ['sometimes', Rule::enum(FunctionWebhookMethod::class)],
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
            $function = $this->findFunction();
            $canKeep = $function !== null && $function->auth_type->value === $type && $function->auth_config !== null;
            if (! $canKeep) {
                $validator->errors()->add('auth_value', 'A credential value is required for this auth type.');
            }
        });
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
            auth_header_name: filled($this->input('auth_header_name')) ? (string) $this->input('auth_header_name') : null,
            auth_value: filled($this->input('auth_value')) ? (string) $this->input('auth_value') : null,
            requires_approval: $this->has('requires_approval') ? $this->boolean('requires_approval') : new Optional,
        );
    }

    /**
     * The function being edited. Authorization already 403s when it isn't one
     * of the workspace's functions.
     */
    public function function(): AgentFunction
    {
        $function = $this->findFunction();
        abort_if($function === null, 404);

        return $function;
    }

    private function findFunction(): ?AgentFunction
    {
        if ($this->function !== null) {
            return $this->function;
        }

        /** @var User $user */
        $user = $this->user();

        return $this->function = $user->workspace()->functions()
            ->where('external_id', $this->route('external_id'))
            ->first();
    }
}
