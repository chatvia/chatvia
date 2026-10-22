<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\Web;

use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates the URLs to remove from a website in one call: at most
 * {@see MAX_LINKS}, all of them pages of the website in the route.
 */
class BulkDestroyWebsiteLinksRequest extends FormRequest
{
    /** Most URLs one call may remove, so one request stays a few short statements. */
    public const int MAX_LINKS = 500;

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
            'link_ids' => ['required', 'array', 'max:'.self::MAX_LINKS],
            'link_ids.*' => ['required', 'integer'],
        ];
    }

    /**
     * Every id must be a URL of the website in the route, checked with one
     * count instead of one lookup per id. The website's workspace is checked
     * by the `workspace.scope` route guard.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = array_values(array_unique(array_map(intval(...), (array) $this->input('link_ids'))));
                $website = $this->route('website');
                $owned = $website instanceof SourceWebsite ? $website->urls()->whereIn('id', $ids)->count() : 0;

                if ($owned !== count($ids)) {
                    $validator->errors()->add('link_ids', __('validation.exists', ['attribute' => $validator->getDisplayableAttribute('link_ids')]));
                }
            },
        ];
    }

    /**
     * @return list<int>
     */
    public function linkIds(): array
    {
        return array_values(array_unique(array_map(intval(...), (array) $this->validated('link_ids'))));
    }
}
