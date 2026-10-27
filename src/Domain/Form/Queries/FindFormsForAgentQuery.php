<?php

namespace Domain\Form\Queries;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Data\FormMatchData;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Form\Services\FormHybridSearch;

/**
 * Form discovery for the model: the agent's active forms that fit a visitor's
 * stated intent, each flagged when this visitor's session already submitted
 * it (so the agent acknowledges the submission instead of showing the form
 * again). Without a conversation there is no session, so nothing is flagged.
 */
class FindFormsForAgentQuery
{
    public function __construct(private readonly FormHybridSearch $search) {}

    /**
     * @return list<FormMatchData>
     */
    public function get(Agent $agent, ?Conversation $conversation, string $intent): array
    {
        $matches = $this->search->forPrompt($agent, $intent);

        if ($matches->isEmpty()) {
            return [];
        }

        $submittedIds = $conversation?->chat_session_id !== null
            ? FormSubmission::submittedFormIdsForSession((int) $conversation->chat_session_id, $matches->pluck('id')->all())
            : [];

        return $matches->map(static fn (FormDefinition $form): FormMatchData => new FormMatchData(
            slug: $form->slug,
            name: $form->name,
            purpose: $form->agent_instructions,
            alreadySubmitted: collect($submittedIds)->containsStrict($form->id),
        ))->values()->all();
    }
}
