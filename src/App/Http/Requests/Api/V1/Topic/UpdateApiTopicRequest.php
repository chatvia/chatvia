<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Topic;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `PATCH /api/v1/agents/{agent}/topics/{topic}` — rename / re-describe one of
 * the workspace's own topics (system topics are read-only).
 */
class UpdateApiTopicRequest extends FormRequest
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
            // The topic's new name.
            'topic' => ['required', 'string', 'max:120'],
            // What conversations the topic covers — written for the analysis model.
            'description' => ['required', 'string', 'max:255'],
        ];
    }
}
