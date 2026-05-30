<?php

namespace App\Http\Requests\Agents;

use App\Auth\Models\User;
use Domain\Agents\Data\ToggleAgentLlmData;
use Domain\Agents\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;

class ToggleAgentModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();
        /** @var Agent $agent */
        $agent = $this->route('agent');

        return $user !== null && $user->can('view', $agent);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model_id' => ['required', 'exists:llms,external_name'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function toData(): ToggleAgentLlmData
    {
        return new ToggleAgentLlmData(
            model_id: $this->string('model_id')->toString(),
            status: $this->boolean('status'),
        );
    }
}
