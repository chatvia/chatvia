<?php

namespace Domain\Form\Actions;

use Domain\Form\Data\RewriteFormDescriptionData;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Operations\Query\TitleGenerator;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;

/**
 * Rewrite a form's "when to show this form" instruction into a clear, specific
 * trigger description, so the agent renders the form by visitor INTENT and stops
 * over-triggering it on general questions. One short LLM call, text in / text out
 * (mirrors {@see TitleGenerator}). A blank reply is refused, so the editor keeps
 * the owner's draft instead of replacing it with nothing.
 */
class RewriteFormDescriptionAction
{
    /**
     * Room for the reply. The instruction is one or two sentences, but a
     * reasoning model (gpt-oss, gpt-5…) spends tokens thinking first: with a
     * tight cap it stops before writing anything and the reply is empty.
     */
    private const int MAX_TOKENS = 800;

    public function __construct(
        private readonly LlmChat $chat,
        private readonly TaskModels $models,
    ) {}

    /**
     * @throws DomainValidationException when the model wrote nothing
     */
    public function execute(RewriteFormDescriptionData $data): string
    {
        $context = collect([
            'Form name: '.(filled($data->name) ? Str::trim($data->name) : '(unnamed)'),
            'Form type: '.(filled($data->form_type) ? Str::trim($data->form_type) : 'generic'),
            'Current instruction: '.(filled($data->agent_instructions) ? Str::trim($data->agent_instructions) : '(empty)'),
        ])->implode("\n");

        $content = $this->chat->send(new ChatRequestData(
            model: $this->models->model(LlmTask::FormText),
            messages: [
                ChatMessageData::system(prompt('forms/rewrite-description')),
                ChatMessageData::user($context),
            ],
            tag: AiCallTag::FormDescription,
            temperature: 0.4,
            maxTokens: self::MAX_TOKENS,
        ))->content;

        // Models routinely wrap a single-line answer in quotes — strip them.
        $instructions = Str::of($content)->trim()->trim('"')->trim()->toString();

        if ($instructions === '') {
            throw new DomainValidationException('agent_instructions', __('Sorry — something went wrong. Please try again.'));
        }

        return $instructions;
    }
}
