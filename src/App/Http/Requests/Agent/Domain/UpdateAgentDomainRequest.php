<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Domain;

use Domain\Agent\Data\Domain\SaveAgentDomainData;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentDomain;
use Domain\Agent\Services\CustomDomainLookup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The custom domain of an agent's Hosted Chat, such as help.yourshop.com.
 * A pasted address keeps only its host. `agent.permission:agent.setup` and
 * `license.custom-domains` gate the route.
 */
class UpdateAgentDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Agent $agent */
        $agent = $this->route('agent');

        return [
            'host' => [
                'required',
                'string',
                'max:253',
                'regex:'.AgentDomain::HOST_PATTERN,
                // Install-wide, not per workspace: one domain serves one agent.
                Rule::unique('agent_domains', 'host')->ignore($agent->id, 'agent_id'),
            ],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (app(CustomDomainLookup::class)->isOwnHost($this->string('host')->toString())) {
                    $validator->errors()->add('host', __('Use a domain of your own, not the address of this server.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'host.regex' => __('Enter a domain name, such as help.yourshop.com.'),
            'host.unique' => __('Another agent already uses this domain.'),
        ];
    }

    public function toData(Agent $agent): SaveAgentDomainData
    {
        return new SaveAgentDomainData(agent: $agent, host: $this->string('host')->toString());
    }

    protected function prepareForValidation(): void
    {
        // "https://Help.YourShop.com/chat" becomes "help.yourshop.com".
        $host = Str::of($this->string('host')->toString())
            ->trim()
            ->lower()
            ->replaceMatches('#^[a-z][a-z0-9+.-]*://#', '')
            ->before('/')
            ->before('?')
            ->before(':')
            ->rtrim('.')
            ->toString();

        $this->merge(['host' => $host]);
    }
}
