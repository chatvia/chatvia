<?php

namespace App\Http\Requests\Api\V1\Source;

use Domain\Agent\Rules\SafeWebhookUrl;
use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a website added as a knowledge source through the public API.
 * Access to the agent is checked by the route middleware.
 */
class CrawlSourceUrlRequest extends FormRequest
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
            'url' => ['required', 'url', new SafeWebhookUrl],
            'include_paths' => ['sometimes', 'nullable', 'string'],
            'exclude_paths' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function toData(): CrawlerData
    {
        return CrawlerData::from($this->validated());
    }
}
