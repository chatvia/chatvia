<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source;

use Domain\Knowledge\Source\Web\Enums\RefreshInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Changes a website source: how often it is crawled and trained again on its
 * own.
 */
class UpdateAgentWebsiteRequest extends FormRequest
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
            'refresh_interval' => ['required', Rule::enum(RefreshInterval::class)],
        ];
    }

    public function refreshInterval(): RefreshInterval
    {
        return RefreshInterval::from($this->string('refresh_interval')->toString());
    }
}
