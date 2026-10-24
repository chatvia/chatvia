<?php

namespace Domain\Form\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Data\InlineFormData;
use Domain\Form\Data\RenderFormOutcomeData;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Form\Services\FormSchemaBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Show one of the agent's active forms inside a conversation. It resolves the
 * form the model asked for against the agent's REAL active forms only (never a
 * made-up default), refuses a form this visitor's session already submitted,
 * and otherwise issues a fresh pending form instance: a FormSubmission row
 * that {@see SubmitFormAction} later claims when the visitor submits.
 *
 * The pending row carries no IP address or user agent: this runs for
 * Chatvia's own server, not the visitor, so those values would describe us.
 * The visitor's real values are recorded when they submit.
 */
class RenderInlineFormAction
{
    /** How long a rendered form stays submittable. */
    private const int TTL_HOURS = 6;

    /** How many active slugs a "not found" outcome lists for the model to retry with. */
    private const int SLUG_HINT_LIMIT = 8;

    private const string SUBMIT_LABEL = 'Submit';

    private const string DEFAULT_SUCCESS_MESSAGE = 'Thanks — your details have been received.';

    public function __construct(private readonly FormSchemaBuilder $schemas) {}

    /**
     * @param  string  $requestedForm  The slug the model asked for — or, tolerated, the form's display name.
     */
    public function execute(Agent $agent, Conversation $conversation, string $requestedForm): RenderFormOutcomeData
    {
        // Oldest first, so the match and the slug hint don't depend on row order.
        // Only the columns the match and the rendered form use: not the
        // embedding or search text.
        $active = FormDefinition::query()
            ->forAgent($agent)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'agent_id', 'slug', 'name', 'subtitle', 'form_type', 'schema', 'success_message']);

        $definition = $this->match($active, $requestedForm);

        if (! $definition instanceof FormDefinition) {
            return RenderFormOutcomeData::notFound($active->take(self::SLUG_HINT_LIMIT)->pluck('slug')->all());
        }

        if ($this->alreadySubmitted($conversation, $definition)) {
            return RenderFormOutcomeData::alreadySubmitted($definition->name);
        }

        $submission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
            'chat_conversation_id' => $conversation->id,
            'status' => FormSubmissionStatus::Pending,
            'expires_at' => now()->addHours(self::TTL_HOURS),
        ]);

        return RenderFormOutcomeData::rendered(new InlineFormData(
            formInstanceId: $submission->external_id,
            definitionSlug: $definition->slug,
            formType: $definition->form_type,
            title: $definition->name,
            subtitle: $definition->subtitle,
            schema: $this->schemas->toFormKitSchema($definition->schema),
            submitLabel: self::SUBMIT_LABEL,
            successMessage: $definition->success_message ?: self::DEFAULT_SUCCESS_MESSAGE,
            status: FormSubmissionStatus::Pending,
            expiresAt: $submission->expires_at,
        ));
    }

    /**
     * Small models routinely echo a form's display name ("Book a demo") or a
     * re-slugified guess instead of the exact slug, so match tolerantly: the
     * exact slug, the slugified value, or the name.
     *
     * @param  Collection<int, FormDefinition>  $active
     */
    private function match(Collection $active, string $requested): ?FormDefinition
    {
        // Underscores would otherwise be dropped (book_a_demo → bookademo), so
        // normalize snake_case to spaces before slugifying.
        $slugified = Str::slug(Str::replace('_', ' ', $requested));

        return $active->first(static fn (FormDefinition $form): bool => $form->slug === $requested
            || $form->slug === $slugified
            || Str::slug($form->name) === $slugified
            || Str::lower($form->name) === Str::lower($requested));
    }

    /**
     * Scoped to the session (the visitor's identity across conversations), so
     * a booking made earlier in the same session still counts.
     */
    private function alreadySubmitted(Conversation $conversation, FormDefinition $definition): bool
    {
        return $conversation->chat_session_id !== null
            && FormSubmission::submittedFormIdsForSession((int) $conversation->chat_session_id, [$definition->id]) !== [];
    }
}
