<?php

namespace Domain\Form\Actions;

use Domain\Form\Data\RewriteFormDescriptionData;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Operations\Query\TitleGenerator;
use Domain\Llm\Services\LlmChat;
use Illuminate\Support\Str;

/**
 * Rewrite a form's "when to show this form" instruction into a clear, specific
 * trigger description, so the agent renders the form by visitor INTENT and stops
 * over-triggering it on general questions. One short LLM call, text in / text out
 * (mirrors {@see TitleGenerator}).
 */
class RewriteFormDescriptionAction
{
    public function __construct(private readonly LlmChat $chat) {}

    public function execute(RewriteFormDescriptionData $data): string
    {
        $context = collect([
            'Form name: '.(filled($data->name) ? trim($data->name) : '(unnamed)'),
            'Form type: '.(filled($data->form_type) ? trim($data->form_type) : 'generic'),
            'Current instruction: '.(filled($data->agent_instructions) ? trim($data->agent_instructions) : '(empty)'),
        ])->implode("\n");

        $content = $this->chat->send(new ChatRequestData(
            model: (string) config('chatvia.ai.models.form_description'),
            messages: [
                ChatMessageData::system(prompt('forms/rewrite-description')),
                ChatMessageData::user($context),
            ],
            temperature: 0.4,
            maxTokens: 160,
        ))->content;

        // Models routinely wrap a single-line answer in quotes — strip them.
        return Str::of($content)->trim()->trim('"')->trim()->toString();
    }
}
