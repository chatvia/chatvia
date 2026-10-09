<?php

namespace App\Mcp\Tools\Forms;

use App\Mcp\McpCallerContext;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Data\FormMatchData;
use Domain\Form\Queries\FindFormsForAgentQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Form discovery: the model asks which of the agent's forms fit what the
 * visitor wants, then shows one with render-form. Nothing is injected into
 * the system prompt — forms are found on demand.
 */
#[Name('find-forms')]
#[Description('Discover whether the agent has a fillable form that matches what the visitor wants to do — e.g. book a demo, request a quote or callback, sign up, or be contacted. Pass the visitor\'s intent in plain words; returns the matching forms, each with a `slug`, name and purpose. Call this the moment a visitor expresses such an intent, then call render-form with the chosen slug. If it returns no forms, or none of them fit the visitor\'s need, keep helping conversationally and do NOT render a form. Do not call this for general or informational questions.')]
#[IsReadOnly]
class FindFormsTool extends Tool
{
    public function handle(Request $request, McpCallerContext $caller, FindFormsForAgentQuery $forms): Response|ResponseFactory
    {
        if ($caller->agentExternalId === null) {
            return Response::error('Missing X-Chatvia-Agent-Id identity header.');
        }

        if (! $caller->agent instanceof Agent) {
            return Response::error("Unknown agent \"{$caller->agentExternalId}\".");
        }

        // Forms only render on the web surfaces. On messaging channels (Telegram,
        // Slack, …) there's no inline-form artifact, so don't surface forms at all —
        // keep the model helping conversationally, mirroring RenderFormTool.
        if ($caller->conversation instanceof Conversation && ! $caller->conversation->source->isWeb()) {
            return Response::make(Response::text(
                'This channel cannot display forms. Keep helping the visitor conversationally.'
            ))->withStructuredContent(['forms' => []]);
        }

        $validated = $request->validate([
            'intent' => ['required', 'string', 'max:500'],
        ]);

        $matches = $forms->get($caller->agent, $caller->conversation, $validated['intent']);

        if ($matches === []) {
            return Response::make(Response::text(
                'No fillable form matches that intent. Keep helping the visitor conversationally — do not render a form.'
            ))->withStructuredContent(['forms' => []]);
        }

        // Forms this visitor already completed are flagged so the model
        // acknowledges the submission instead of rendering the form again
        // (render-form also refuses).
        $text = array_any($matches, static fn (FormMatchData $match): bool => $match->alreadySubmitted)
            ? 'Matching forms found. Forms marked already_submitted were already completed by this visitor — do NOT render those again; acknowledge the submission. For any other form that fits, call render-form with its slug.'
            : 'Matching forms found. If one fits what the visitor wants, call render-form with its slug; otherwise keep helping without a form.';

        return Response::make(Response::text($text))->withStructuredContent([
            'forms' => array_map(static fn (FormMatchData $match): array => [
                'slug' => $match->slug,
                'name' => $match->name,
                'purpose' => $match->purpose,
                'already_submitted' => $match->alreadySubmitted,
            ], $matches),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'intent' => $schema->string()
                ->description('What the visitor wants to do, in plain words (e.g. "book a demo", "get a price quote", "talk to sales").')
                ->required(),
        ];
    }
}
