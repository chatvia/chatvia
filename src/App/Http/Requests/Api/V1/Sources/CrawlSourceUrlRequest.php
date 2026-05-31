<?php

namespace App\Http\Requests\Api\V1\Sources;

use Domain\Sources\Web\Data\Crawler\CrawlerData;
use Illuminate\Foundation\Http\FormRequest;

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
            'url' => ['required', 'url'],
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
