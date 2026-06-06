<?php

namespace App\Http\Requests\Agent\Source\Text;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared rules + authorize for the agent-source-text store + update routes.
 * The Action signature differs (create takes an Agent, update takes a
 * SourceText too), so each controller method still resolves the SourceText
 * via the route param — but validation + authz live here, not inline.
 */
class AgentSourceTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agent = $this->route('agent');

        return $agent !== null && $this->user()?->can('update', $agent);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'min:500'],
        ];
    }
}
