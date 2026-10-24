<?php

namespace App\Mcp\Tools\Forms;

use App\Mcp\McpCallerContext;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Actions\RenderInlineFormAction;
use Domain\Form\Data\InlineFormData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

/**
 * Shows a form inline in the chat. The tool checks who is asking and where,
 * hands the request to {@see RenderInlineFormAction}, and returns the result
 * as a `form` artifact the chat UI draws (or as guidance for the model when
 * nothing was shown).
 */
#[Description('Render a fillable form inline in the chat (lead capture, booking, qualification, or a custom form the agent admin built). The visitor fills and submits it; the form stores the answers itself. Call find-forms first to discover which forms exist and which fits the visitor\'s intent, then pass the exact `slug` it returned. Only render a slug find-forms returned — never invent a slug or fall back to a default form.')]
#[IsOpenWorld]
class RenderFormTool extends Tool
{
    public function handle(Request $request, McpCallerContext $caller, RenderInlineFormAction $render): Response|ResponseFactory
    {
        if ($caller->agentExternalId === null) {
            return Response::error('Missing X-Chatvia-Agent-Id identity header.');
        }
        if ($caller->conversationExternalId === null) {
            return Response::error('Missing X-Chatvia-Conversation-Id header — a form can only be rendered inside an active chat conversation.');
        }
        if (! $caller->agent instanceof Agent) {
            return Response::error("Unknown agent \"{$caller->agentExternalId}\".");
        }
        if (! $caller->conversation instanceof Conversation) {
            return Response::error('Conversation not found or does not belong to this agent.');
        }

        // Messaging channels can't render an inline form — keep the model helping
        // conversationally instead.
        if (! $caller->conversation->source->isWeb()) {
            return Response::error('This channel cannot display a form. Keep helping the visitor conversationally instead.');
        }

        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:120'],
        ]);

        $requested = Str::trim($validated['slug']);
        $outcome = $render->execute($caller->agent, $caller->conversation, $requested);

        if ($outcome->form instanceof InlineFormData) {
            return $this->rendered($outcome->form);
        }

        if ($outcome->alreadySubmittedFormName !== null) {
            return Response::make(Response::text(
                "The visitor has already submitted the \"{$outcome->alreadySubmittedFormName}\" form. Do not show it again — let them know their submission was already received and continue helping."
            ));
        }

        // No fabricated default. If the agent has active forms, name their exact
        // slugs so the model retries correctly instead of looping; if it has
        // none, steer it back to conversational help.
        $hint = $outcome->activeSlugs === []
            ? 'This agent has no active form — keep helping the visitor conversationally and do not render a form.'
            : 'Call render-form again with one of this agent\'s exact active slugs: '.implode(', ', $outcome->activeSlugs).'.';

        return Response::error("No active form matches \"{$requested}\". {$hint}");
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()
                ->description('Exact slug of the form to render, as returned by find-forms. Do not invent one or guess a default.')
                ->required(),
        ];
    }

    private function rendered(InlineFormData $form): ResponseFactory
    {
        return Response::make(Response::text(
            "A form titled \"{$form->title}\" is now displayed to the visitor. Briefly invite them to fill it in — do NOT say where on screen it appears (it is not necessarily \"below\"), and do not ask for these fields in text. The form stores the answers itself when submitted."
        ))->withStructuredContent(['form' => [
            'form_instance_id' => $form->formInstanceId,
            'definition_slug' => $form->definitionSlug,
            'form_type' => $form->formType->value,
            'title' => $form->title,
            'subtitle' => $form->subtitle,
            'schema' => $form->schema,
            'submit_label' => $form->submitLabel,
            'success_message' => $form->successMessage,
            'status' => $form->status->value,
            'expires_at' => $form->expiresAt?->toIso8601String(),
        ]]);
    }
}
