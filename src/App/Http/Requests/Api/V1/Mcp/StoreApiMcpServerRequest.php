<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Mcp;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Custom\AddCustomConnectorData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A custom MCP server added over the public API: a name, the endpoint URL
 * and a bearer token or no auth. OAuth servers need a browser redirect, so
 * they are connected in the dashboard instead.
 */
class StoreApiMcpServerRequest extends FormRequest
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
            // Display name of the server.
            'name' => ['required', 'string', 'max:100'],
            // The server's endpoint URL (streamable HTTP).
            'url' => ['required', 'url:https', 'max:2048'],
            // How calls to the server authenticate: `token` sends the bearer
            // token below; `none` sends nothing. OAuth servers are connected
            // in the dashboard.
            'auth_strategy' => ['required', Rule::enum(ConnectorAuthStrategy::class)->except(ConnectorAuthStrategy::Oauth)],
            // The bearer token, required with the `token` strategy. Stored
            // encrypted and never returned.
            'token' => ['required_if:auth_strategy,token', 'nullable', 'string', 'max:4096'],
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
            auth_strategy: ConnectorAuthStrategy::from($this->string('auth_strategy')->toString()),
            token: $this->filled('token') ? $this->string('token')->toString() : null,
        );
    }
}
