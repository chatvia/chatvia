<?php

namespace App\Http\Requests\Agent\Topic;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentTopicRequest extends FormRequest
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
            'topic' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
        ];
    }
}
