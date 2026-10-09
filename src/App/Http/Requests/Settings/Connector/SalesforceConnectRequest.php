<?php

namespace App\Http\Requests\Settings\Connector;

use Domain\Integration\Connector\Data\Salesforce\SalesforceOrgData;
use Domain\Integration\Connector\Enums\SalesforceEnvironment;
use Domain\Integration\Connector\Services\Salesforce\SalesforceEndpointBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the workspace's Salesforce External Client App details before
 * starting the per-org OAuth + PKCE flow. `my_domain` is constrained to a
 * Salesforce-owned host so it can't be used to point the token exchange at an
 * arbitrary origin (the flow also SSRF-guards it at request time).
 */
class SalesforceConnectRequest extends FormRequest
{
    /**
     * The `team.permission:account.connectors` route middleware gates access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:1024'],
            'environment' => ['required', Rule::enum(SalesforceEnvironment::class)],
            'my_domain' => ['nullable', 'string', 'regex:#^https://[a-z0-9-]+(\.[a-z0-9-]+)*\.(salesforce\.com|force\.com)/?$#i'],
            'server' => ['required', Rule::in(SalesforceEndpointBuilder::SERVERS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'my_domain.regex' => __('Enter your Salesforce My Domain URL (e.g. https://acme.my.salesforce.com).'),
        ];
    }

    public function toData(): SalesforceOrgData
    {
        return new SalesforceOrgData(
            clientId: $this->string('client_id')->toString(),
            clientSecret: $this->input('client_secret'),
            environment: $this->enum('environment', SalesforceEnvironment::class),
            myDomain: $this->input('my_domain'),
            server: $this->string('server')->toString(),
        );
    }
}
