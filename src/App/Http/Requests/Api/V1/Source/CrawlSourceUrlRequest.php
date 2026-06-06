<?php

namespace App\Http\Requests\Api\V1\Source;

use Domain\Knowledge\Source\Web\Data\Crawler\CrawlerData;
use Illuminate\Foundation\Http\FormRequest;
use Domain\Agent\Rules\SafeWebhookUrl;

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
            'slow_crawling' => ['sometimes', 'boolean'],
            'include_paths' => ['sometimes', 'nullable', 'string'],
            'exclude_paths' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function toData(): CrawlerData
    {
        $validated = $this->validated();
        $validated['slow_crawling'] = $validated['slow_crawling'] ?? false;

        return CrawlerData::from($validated);
    }
}
