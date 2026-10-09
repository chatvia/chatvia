<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent;

use Domain\Agent\Data\Settings\UpdateAgentDomainData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

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
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.regex' => 'Enter a valid hostname (e.g. chat.example.com), without scheme or path.',
            'domain.not_in' => 'You can\'t use the platform\'s own host as a custom domain.',
        ];
    }

    public function toData(): UpdateAgentDomainData
    {
        // Normalized hostname (lowercase, trimmed); empty input means "clear".
        $domain = strtolower(trim((string) $this->input('domain')));

        return new UpdateAgentDomainData(
            domain: $domain === '' ? null : $domain,
        );
    }
}
