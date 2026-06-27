<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Domain\Form\Services\FormHybridSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('find-forms')]
#[Description('Discover whether the agent has a fillable form that matches what the visitor wants to do — e.g. book a demo, request a quote or callback, sign up, or be contacted. Pass the visitor\'s intent in plain words; returns the matching forms, each with a `slug`, name and purpose. Call this the moment a visitor expresses such an intent, then call render-form with the chosen slug. If it returns no forms, or none of them fit the visitor\'s need, keep helping conversationally and do NOT render a form. Do not call this for general or informational questions.')]
#[IsReadOnly]
class FindFormsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $http = app(HttpRequest::class);

        $agentExternalId = $http->header('X-Chatvia-Agent-Id');
        if (! $agentExternalId) {
            return Response::error('Missing X-Chatvia-Agent-Id identity header.');
        }

        $agent = Agent::query()->where('external_id', $agentExternalId)->first();
        if (! $agent) {
            return Response::error("Unknown agent \"{$agentExternalId}\".");
        }

        $conversation = null;
        $conversationExternalId = $http->header('X-Chatvia-Conversation-Id');
        if ($conversationExternalId) {
            $conversation = Conversation::query()
                ->where('external_id', $conversationExternalId)
                ->forAgent($agent->id)
                ->first();
        }

        // Forms only render on the web surfaces. On messaging channels (Telegram,
        // Slack, …) there's no inline-form artifact, so don't surface forms at all —
        // keep the model helping conversationally, mirroring RenderFormTool.
        if ($conversation && ! $conversation->source->isWeb()) {
            return Response::make(Response::text(
                'This channel cannot display forms. Keep helping the visitor conversationally.'
            ))->withStructuredContent(['forms' => []]);
        }

        $validated = $request->validate([
            'intent' => ['required', 'string', 'max:500'],
        ]);

        $matches = app(FormHybridSearch::class)->forPrompt($agent, $validated['intent']);

        if ($matches->isEmpty()) {
            return Response::make(Response::text(
                'No fillable form matches that intent. Keep helping the visitor conversationally — do not render a form.'
            ))->withStructuredContent(['forms' => []]);
        }

        // Flag forms this visitor already completed so the model acknowledges the
        // submission instead of rendering the form again (render-form also refuses).
        $submittedIds = $conversation?->chat_session_id !== null
            ? FormSubmission::submittedFormIdsForSession((int) $conversation->chat_session_id, $matches->pluck('id')->all())
            : [];

        $forms = $matches->map(fn (FormDefinition $form): array => [
            'slug' => $form->slug,
            'name' => $form->name,
            'purpose' => $form->agent_instructions,
            'already_submitted' => in_array($form->id, $submittedIds, true),
        ])->values()->all();

        $text = $submittedIds !== []
            ? 'Matching forms found. Forms marked already_submitted were already completed by this visitor — do NOT render those again; acknowledge the submission. For any other form that fits, call render-form with its slug.'
            : 'Matching forms found. If one fits what the visitor wants, call render-form with its slug; otherwise keep helping without a form.';

        return Response::make(Response::text($text))->withStructuredContent(['forms' => $forms]);
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
