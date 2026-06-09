<?php

namespace App\Http\Requests\Settings\Connector;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the workspace's Salesforce External Client App details before
 * starting the per-org OAuth + PKCE flow. `my_domain` is constrained to a
 * Salesforce-owned host so it can't be used to point the token exchange at an
 * arbitrary origin (the flow also SSRF-guards it at request time).
 */
class SalesforceConnectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return has_team_permission('account.connectors');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:1024'],
            'environment' => ['required', 'in:production,sandbox'],
            'my_domain' => ['nullable', 'string', 'regex:#^https://[a-z0-9-]+(\.[a-z0-9-]+)*\.(salesforce\.com|force\.com)/?$#i'],
            'server' => ['required', 'in:sobject-all,sobject-reads,invocable-actions,flows,data-360,prompt-builder,tableau-next'],
        ];
    }

    public function messages(): array
    {
        return [
            'my_domain.regex' => 'Enter your Salesforce My Domain URL (e.g. https://acme.my.salesforce.com).',
        ];
    }
}
