<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent\Source\Web;

use App\Http\Rules\WorkspaceRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates linking an existing website to an agent. Whether the caller may
 * see the website is checked by the website policy in the controller.
 */
class LinkSourceWebsiteRequest extends FormRequest
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
            'website_id' => ['required', WorkspaceRule::exists('source_websites', $this->user()->workspace())],
        ];
    }
}
