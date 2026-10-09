<?php

namespace App\Http\Requests\Settings\Connector;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Integration\Connector\Data\Account\ConnectTokenConnectorData;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Connect a token or no-auth catalog connector (the `{slug}` route segment).
 * A token connector needs the token; a preset without a first-party MCP
 * server (e.g. Zendesk) also needs the workspace's own server URL. An unknown,
 * OAuth-only or coming-soon connector is a 404.
 */
class ConnectTokenConnectorRequest extends FormRequest
{
    private ?ConnectorData $connector = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $connector = $this->connector();

        $rules = [
            'token' => $connector->authStrategy === ConnectorAuthStrategy::Token
                ? ['required', 'string', 'max:8192']
                : ['sometimes', 'nullable', 'string', 'max:8192'],
        ];

        if ($connector->requiresUrl) {
            $rules['url'] = ['required', 'url', 'max:2048', new SafeWebhookUrl];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => __('A token is required for this connector.'),
        ];
    }

    public function toData(): ConnectTokenConnectorData
    {
        /** @var User $user */
        $user = $this->user();
        $connector = $this->connector();

        return new ConnectTokenConnectorData(
            user: $user,
            connector: $connector,
            token: $connector->authStrategy === ConnectorAuthStrategy::Token ? $this->string('token')->toString() : null,
            url: $connector->requiresUrl ? $this->string('url')->toString() : null,
        );
    }

    private function connector(): ConnectorData
    {
        $this->connector ??= app(ConnectorCatalog::class)->findTokenConnector((string) $this->route('slug'));

        abort_if($this->connector === null, 404, __('Connector not available'));

        return $this->connector;
    }
}
