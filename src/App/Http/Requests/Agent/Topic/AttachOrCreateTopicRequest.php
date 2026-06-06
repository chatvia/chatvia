<?php

namespace App\Http\Requests\Agent\Topic;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Two-shape input for AgentTopicController::store: when the caller passes
 * `topic_id` we attach an existing topic; otherwise we create a fresh one
 * from `topic` + `description`. Validation runs the right branch via
 * `sometimes` rules.
 */
class AttachOrCreateTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agent = $this->route('agent');

        return $agent !== null && $this->user()?->can('update', $agent);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        if ($this->has('topic_id')) {
            return [
                'topic_id' => ['required', 'integer', 'exists:topics,id'],
            ];
        }

        return [
            'topic' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:500'],
        ];
    }

    public function isAttach(): bool
    {
        return $this->has('topic_id');
    }
}
