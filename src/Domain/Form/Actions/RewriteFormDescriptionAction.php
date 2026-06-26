<?php

namespace Domain\Form\Actions;

use Domain\Form\Data\RewriteFormDescriptionData;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Operations\Query\TitleGenerator;
use Illuminate\Support\Str;

/**
 * Rewrite a form's "when to show this form" instruction into a clear, specific
 * trigger description, so the agent renders the form by visitor INTENT and stops
 * over-triggering it on general questions. One short LLM call, text in / text out
 * (mirrors {@see TitleGenerator}).
 */
class RewriteFormDescriptionAction
{
    public function __construct(private readonly LlmProviderInterface $provider) {}

    public function execute(RewriteFormDescriptionData $data): string
    {
        $context = collect([
            'Form name: '.(filled($data->name) ? trim($data->name) : '(unnamed)'),
            'Form type: '.(filled($data->form_type) ? trim($data->form_type) : 'generic'),
            'Current instruction: '.(filled($data->description) ? trim($data->description) : '(empty)'),
        ])->implode("\n");

        $content = $this->provider->completion([
            'model' => config('chatvia.ai.models.form_description'),
            'temperature' => 0.4,
            'max_tokens' => 160,
            'messages' => [
                ['role' => 'system', 'content' => prompt('forms/rewrite-description')],
                ['role' => 'user', 'content' => $context],
            ],
        ])->throw()->json('choices.0.message.content');

        // Models routinely wrap a single-line answer in quotes — strip them.
        return Str::of((string) $content)->trim()->trim('"')->trim()->toString();
    }
}
