<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent;

use Domain\Agent\Data\Settings\UpdateAgentDomainData;
use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Illuminate\Validation\Rule;

/**
 * Validates the custom domain of an agent's Hosted Chat. The value must be a
 * bare hostname (no scheme or path), must not be the platform's own host, and
 * must not belong to another agent; an empty value clears the domain. Agent
 * access and the `agent.setup` permission are checked by the route
 * middleware.
 */
class UpdateAgentDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $appHost = Uri::of((string) config('app.url'))->host();

        /** @var Agent $agent */
        $agent = $this->route('agent');

        return [
            'domain' => [
                'nullable',
                'string',
                'max:255',
                // RFC-1123 hostname: labels of letters/digits/hyphens
                // separated by dots, no leading/trailing hyphen per label.
                'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
                // Don't let the user pin their custom domain to the app's
                // own host (APP_URL's host): it must never resolve to a
                // workspace-controlled record.
                Rule::notIn([$appHost]),
                // A custom domain shows one agent's chat.
                Rule::unique('agents', 'domain')->ignore($agent->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.regex' => __('Enter a valid hostname (e.g. chat.example.com), without scheme or path.'),
            'domain.not_in' => __("You can't use the platform's own host as a custom domain."),
        ];
    }

    public function toData(): UpdateAgentDomainData
    {
        // Normalized hostname (lowercase, trimmed); empty input means "clear".
        $domain = Str::lower(Str::trim((string) $this->input('domain')));

        return new UpdateAgentDomainData(
            domain: $domain === '' ? null : $domain,
        );
    }
}
