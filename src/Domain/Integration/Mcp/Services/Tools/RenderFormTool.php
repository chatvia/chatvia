<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Form\Services\DefaultLeadForm;
use Domain\Form\Services\FormSchemaBuilder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Render a fillable form inline in the chat (lead capture, booking, qualification, or a custom form the agent admin built). The visitor fills and submits it; the form stores the answers itself. Pass the form `slug` returned by find-forms to choose a specific form; omit it to show the agent\'s default active form. Call find-forms first to discover which forms exist and which fits the visitor\'s intent.')]
#[IsOpenWorld]
class RenderFormTool extends Tool
{
    /** How long a rendered form stays submittable. */
    private const TTL_HOURS = 6;

    public function handle(Request $request): Response|ResponseFactory
    {
        $http = app(HttpRequest::class);

        $agentExternalId = $http->header('X-Chatvia-Agent-Id');
        $conversationExternalId = $http->header('X-Chatvia-Conversation-Id');

        if (! $agentExternalId) {
            return Response::error('Missing X-Chatvia-Agent-Id identity header.');
        }
        if (! $conversationExternalId) {
            return Response::error('Missing X-Chatvia-Conversation-Id header — a form can only be rendered inside an active chat conversation.');
        }

        $agent = Agent::query()->where('external_id', $agentExternalId)->first();
        if (! $agent) {
            return Response::error("Unknown agent \"{$agentExternalId}\".");
        }

        $conversation = Conversation::query()
            ->where('external_id', $conversationExternalId)
            ->forAgent($agent->id)
            ->first();

        if (! $conversation) {
            return Response::error('Conversation not found or does not belong to this agent.');
        }

        // Messaging channels can't render an inline form — keep the model helping
        // conversationally instead.
        if (! $conversation->source->isWeb()) {
            return Response::error('This channel cannot display a form. Keep helping the visitor conversationally instead.');
        }

        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:64'],
        ]);

        $query = FormDefinition::query()->forAgent($agent)->where('is_active', true);

        if (! empty($validated['slug'])) {
            $definition = (clone $query)->where('slug', $validated['slug'])->first();
            if (! $definition) {
                return Response::error("No active form \"{$validated['slug']}\" is configured for this agent.");
            }
        } else {
            // Default to the agent's first custom form, or auto-provision a lead
            // form from the existing Leads field settings so forms work out of
            // the box before any custom form is built.
            $definition = $query->orderBy('id')->first() ?? app(DefaultLeadForm::class)->forAgent($agent);
        }

        // Don't re-render a form this visitor already completed. Scoped to the
        // session (the visitor's identity across conversations), so a booking made
        // earlier in the same session still counts — the agent acknowledges it
        // instead of showing the form again.
        if ($conversation->chat_session_id !== null
            && FormSubmission::submittedFormIdsForSession((int) $conversation->chat_session_id, [$definition->id]) !== []) {
            return Response::make(Response::text(
                "The visitor has already submitted the \"{$definition->name}\" form. Do not show it again — let them know their submission was already received and continue helping."
            ));
        }

        $submission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'agent_id' => $agent->id,
            'chat_conversation_id' => $conversation->id,
            'status' => FormSubmission::STATUS_PENDING,
            'ip_address' => $http->ip(),
            'user_agent' => $http->userAgent(),
            'expires_at' => now()->addHours(self::TTL_HOURS),
        ]);

        $artifact = [
            'form_instance_id' => $submission->external_id,
            'definition_slug' => $definition->slug,
            'form_type' => $definition->form_type->value,
            'title' => $definition->name,
            'subtitle' => $definition->subtitle,
            'schema' => app(FormSchemaBuilder::class)->toFormKitSchema($definition->schema),
            'submit_label' => 'Submit',
            'success_message' => $definition->success_message
                ?: 'Thanks — your details have been received.',
            'status' => FormSubmission::STATUS_PENDING,
            'expires_at' => $submission->expires_at?->toIso8601String(),
        ];

        return Response::make(Response::text(
            "A form titled \"{$definition->name}\" has been shown to the visitor below. Wait for them to fill and submit it — do not ask for these fields in text. The form stores the answers itself when submitted."
        ))->withStructuredContent(['form' => $artifact]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->description('Optional slug of the specific form to render. Omit to show the agent\'s default active form.'),
        ];
    }
}
