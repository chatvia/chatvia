<?php

namespace App\Http\Requests\Agent\Source\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the URLs to remove from a website in one call.
 */
class BulkDestroyWebsiteLinksRequest extends FormRequest
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
            'link_ids' => ['required', 'array'],
            'link_ids.*' => ['required', 'integer', 'exists:source_website_urls,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function linkIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->validated('link_ids')));
    }
}
