<?php

namespace App\Services;

use App\Models\ChatTopic;
use App\Services\Infrastructure\LLM\OpenAI;

class TopicAnalysis
{
    public function analyze(string $text): ChatTopic
    {
        $topics = ChatTopic::query()
            ->select('id', 'topic')
            ->pluck('topic', 'id')
            ->toJson();

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
