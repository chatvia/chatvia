<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Function;

use App\Http\Requests\Concerns\ValidatesFunctionAuth;
use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Integration\Function\Data\FunctionDefinitionData;
use Domain\Integration\Function\Rules\ValidParametersSchema;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/functions` — define a new webhook function. Credentials
 * arrive flat (`auth_value`, `auth_header_name`) and are stored encrypted.
 */
class StoreApiFunctionRequest extends FormRequest
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
        return [
            // The name the model calls the function by: letters, digits and
            // underscores, starting with a letter (e.g. `get_order_status`).
            'internal_name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            // A human-friendly name shown in the dashboard and chat activity.
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            // What the function does and when to call it — written for the model.
            'description' => ['required', 'string', 'max:1024'],
            // A JSON Schema object describing the function's arguments.
            'parameters_schema' => ['required', 'array', new ValidParametersSchema],
            // The HTTPS endpoint the function call is sent to.
            'webhook_url' => ['required', 'url', 'max:2048', new SafeWebhookUrl],
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
