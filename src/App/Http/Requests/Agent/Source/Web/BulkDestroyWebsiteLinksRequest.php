<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // Only URLs of the website in the route; its workspace is checked
            // by the `workspace.scope` route guard.
            'link_ids.*' => ['required', 'integer', Rule::exists('source_website_urls', 'id')->where('source_website_id', $this->route('website')?->id)],
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
