<?php

namespace App\Http\Requests\Agent\Source\Qa;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentQaRequest extends FormRequest
{
    use AgentQaRequestRules;

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
        return $this->qaRules();
    }
}
