<?php

namespace App\Http\Requests\Connector;

use App\Http\Controllers\Concerns\BindsConnectorOAuthToSession;
use Domain\Integration\Connector\Data\OAuth\ConnectorOAuthCallbackData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The provider's redirect back after the OAuth consent screen. On success it
 * carries `state` + `code`; when the user declined (or the provider failed)
 * it carries `error` (+ `error_description`) instead.
 */
class ConnectorOAuthCallbackRequest extends FormRequest
{
    use BindsConnectorOAuthToSession;

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
            'state' => ['nullable', 'string'],
            'code' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'error_description' => ['nullable', 'string'],
        ];
    }

    /**
     * The reason the provider gave for not granting access, or null when it
     * did grant it.
     */
    public function upstreamError(): ?string
    {
        $error = $this->string('error')->toString();

        if ($error === '') {
            return null;
        }

        return $this->string('error_description')->toString() ?: $error;
    }

    /**
     * The callback to complete, or null when `state` or `code` is missing.
     */
    public function toData(): ?ConnectorOAuthCallbackData
    {
        $state = $this->string('state')->toString();
        $code = $this->string('code')->toString();

        if ($state === '' || $code === '') {
            return null;
        }

        return new ConnectorOAuthCallbackData(
            state: $state,
            code: $code,
            sessionBinding: $this->connectorOAuthSessionBinding($this),
        );
    }
}
