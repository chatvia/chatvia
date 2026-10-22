<?php

namespace App\Http\Requests\Agent\ChatLog;

use Domain\Agent\Data\ChatLog\ChatLogFiltersData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The cursor and filters of the chat-log list. The values are matched against
 * known options by the conversation query, so any string is accepted here.
 * Authorization is the route's `agent.permission:agent.chat.view` middleware.
 */
class ListChatLogConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function toData(): ChatLogFiltersData
    {
        return new ChatLogFiltersData(
            cursor: $this->queryString('cursor'),
            topic: $this->queryString('topic'),
            sentiment: $this->queryString('sentiment'),
            tone: $this->queryString('tone'),
            startDate: $this->queryString('start_date'),
            endDate: $this->queryString('end_date'),
            country: $this->queryString('country'),
            confidenceScore: $this->queryString('confidence_score'),
            feedback: $this->queryString('feedback'),
            source: $this->queryString('source'),
            onlineOnly: $this->boolean('online_only'),
        );
    }

    private function queryString(string $key): ?string
    {
        $value = $this->query($key);

        return filled($value) && ! is_array($value) ? $value : null;
    }
}
