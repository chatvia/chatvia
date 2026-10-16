<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Function;

use App\Http\Requests\Concerns\ValidatesFunctionAuth;
use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Integration\Function\Data\FunctionDefinitionData;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Function\Rules\ValidParametersSchema;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `PATCH /api/v1/functions/{function}` — change only the fields sent. When
 * `auth_type` is sent, the matching credential must be sent with it.
 */
class UpdateApiFunctionRequest extends FormRequest
{
    use ValidatesFunctionAuth;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $function = $this->route('function');
        $currentSchema = $function instanceof AgentFunction ? $function->parameters_schema?->getArrayCopy() : null;

        return [
            // The name the model calls the function by: letters, digits and
            // underscores, starting with a letter (e.g. `get_order_status`).
            'internal_name' => ['sometimes', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            // A human-friendly name shown in the dashboard and chat activity.
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            // What the function does and when to call it — written for the model.
            'description' => ['sometimes', 'string', 'max:1024'],
            // A JSON Schema object describing the function's arguments.
            'parameters_schema' => ['sometimes', 'array', new ValidParametersSchema($currentSchema)],
            // The HTTPS endpoint the function call is sent to.
            'webhook_url' => ['sometimes', 'url', 'max:2048', new SafeWebhookUrl],
            // Extra headers to send with every webhook call, as `{name: value}`.
            'webhook_headers' => ['sometimes', 'nullable', 'array'],
            ...$this->functionAuthRules(),
            // How long to wait for the webhook, in milliseconds (1000–30000).
            'timeout_ms' => ['sometimes', 'integer', 'min:1000', 'max:30000'],
            // Whether agents may call the function.
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): FunctionDefinitionData
    {
        return FunctionDefinitionData::from($this->validated());
    }
}
