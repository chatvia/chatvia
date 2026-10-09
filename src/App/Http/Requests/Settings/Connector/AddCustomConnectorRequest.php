<?php

namespace App\Http\Requests\Settings\Connector;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Integration\Connector\Data\Custom\AddCustomConnectorData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add a custom MCP server by URL, with OAuth, a token or no auth. The URL
 * must be a public address (SafeWebhookUrl).
 */
class AddCustomConnectorRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:64'],
            'url' => ['required', 'url', 'max:2048', new SafeWebhookUrl],
            'auth_strategy' => ['required', Rule::enum(ConnectorAuthStrategy::class)],
            'token' => ['sometimes', 'nullable', 'string', 'max:8192'],
        ];
    }

    public function toData(): AddCustomConnectorData
    {
        /** @var User $user */
        $user = $this->user();

        return new AddCustomConnectorData(
            user: $user,
            name: $this->string('name')->toString(),
            url: $this->string('url')->toString(),
            auth_strategy: $this->enum('auth_strategy', ConnectorAuthStrategy::class),
            token: $this->input('token'),
        );
    }
}
