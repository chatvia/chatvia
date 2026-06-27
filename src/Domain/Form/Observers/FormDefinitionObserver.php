<?php

namespace Domain\Form\Observers;

use Domain\Form\Jobs\EmbedFormDefinitionJob;
use Domain\Form\Models\FormDefinition;

/**
 * Keeps a form's semantic-router embedding in step with the fields the router
 * scores against. A queued job re-embeds on create, and on any change to the
 * `name` or `agent_instructions` (the embedded text), so the form ranks correctly on
 * the next turn. Other edits (schema, lead_field_map, is_active) don't change
 * the embedded text — is_active is filtered at query time, not embedded.
 */
class FormDefinitionObserver
{
    public function created(FormDefinition $form): void
    {
        EmbedFormDefinitionJob::dispatch($form->id);
    }

    public function updated(FormDefinition $form): void
    {
        if ($form->wasChanged(['name', 'agent_instructions'])) {
            EmbedFormDefinitionJob::dispatch($form->id);
        }
    }
}
