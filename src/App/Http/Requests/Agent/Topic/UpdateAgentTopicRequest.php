<?php

namespace App\Http\Requests\Agent\Topic;

use Domain\Conversation\Topic\Data\SaveTopicData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new name and description for one of the agent's topics.
 */
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

    public function toData(): SaveTopicData
    {
        return new SaveTopicData(
            topic: $this->string('topic')->toString(),
            description: $this->string('description')->toString(),
        );
    }
}
