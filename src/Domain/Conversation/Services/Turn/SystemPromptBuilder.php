<?php

namespace Domain\Conversation\Services\Turn;

use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Services\ChatContext;

/**
 * Assembles what the LLM receives for a turn: the system prompt (the agent's
 * instructions, the grounding rules, who the visitor is on protected agents,
 * and the business's contact details), then the recent history, then the
 * visitor's message with any retrieved reference material.
 *
 * Forms are not listed here: the agent discovers them through the Forms MCP
 * server (find-forms → render-form), so a form only enters the context when
 * the model searches for one; the web-only channel gate lives in those tools.
 */
class SystemPromptBuilder
{
    /**
     * The OpenAI-shaped message list for the turn. When the history already
     * ends with the visitor's message it is replaced by the enriched one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function messagesFor(ChatContext $context): array
    {
        $history = $context->llmMessages;
        $last = end($history);

        if ($last && $last['role'] === MessageRole::User->value) {
            array_pop($history);
        }

        return [
            ['role' => 'system', 'content' => $this->systemPrompt($context->requestData)],
            ...$history,
            ['role' => MessageRole::User->value, 'content' => $context->payload->message_for_llm],
        ];
    }

    public function systemPrompt(StreamRequestData $data): string
    {
        $agent = $data->agent;
        $prompt = $agent->resolveSystemInstructions().prompt('knowledge/grounding');

        // Visitors of a protected agent had to sign in, so the model may
        // address them by name. Public agents serve anonymous visitors — the
        // name is left out there even when a chatter happens to be signed in.
        if (! $agent->is_public && filled($data->chatterName)) {
            $prompt .= "\n\n".prompt('chatter/identity', ['name' => $data->chatterName]);
        }

        $business = array_filter($agent->settings('business')->toArray(), filled(...));

        if ($business !== []) {
            $prompt .= "\n\n".prompt('business/profile', [
                'email' => $business['email'] ?? null,
                'phone' => $business['phone'] ?? null,
                'website' => $business['website'] ?? null,
                'address' => $business['address'] ?? null,
                'opening_hours' => $business['opening_hours'] ?? null,
            ]);
        }

        return $prompt;
    }
}
