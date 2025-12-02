<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\ChatTopic;
use App\Services\Infrastructure\LLM\OpenAI;

class TopicAnalysis
{
    public function analyze(string $text, ?Agent $agent = null): ?ChatTopic
    {
        // Get topics available for this agent
        $query = ChatTopic::query()->select('id', 'topic');

        if ($agent) {
            // Get topics assigned to this agent
            $topicIds = $agent->topics()->pluck('chat_topics.id');
            $query->whereIn('id', $topicIds);
        }

        $topics = $query->pluck('topic', 'id')->toJson();

        if (empty($topics) || $topics === '{}') {
            return null;
        }

        $topicId = OpenAI::completion([
            'model' => 'gpt-5-nano',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    You are topic analysis expert.
                    Analyze the topic of the given text and respond with one of the following topic ids, choose the most appropriate one: '.$topics.'
                    you must only respond with the topic id.',
                ],
                [
                    'role' => 'user',
                    'content' => "Analyze the topic of the following text:\n\n\"{$text}\"",
                ],
            ],
        ]);

        // get the number from the response only
        $topicId = preg_match('/(\d+)/', $topicId, $matches) ? (int) $matches[1] : null;

        return ChatTopic::find($topicId);
    }
}
