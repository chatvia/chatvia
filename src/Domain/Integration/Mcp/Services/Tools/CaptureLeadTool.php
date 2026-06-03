<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Throwable;

#[Description('Save a contact lead (name, email, phone, and any extra details) so the team can follow up with the visitor. The active agent decides which fields are required.')]
#[IsOpenWorld]
class CaptureLeadTool extends Tool
{
    /**
     * Field keys understood by the leads table — anything else the LLM
     * sends is ignored. The order here drives the order of "missing field"
     * messages so the LLM asks in the order the agent admin configured.
     */
    private const FIELDS = [
        'name',
        'email',
        'phone',
        'job_title',
        'company',
        'location',
        'message',
    ];

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $http = app(HttpRequest::class);

        $agentExternalId = $http->header('X-Chatvia-Agent-Id');
        $conversationExternalId = $http->header('X-Chatvia-Conversation-Id');

        if (! $agentExternalId) {
            return Response::error('Missing X-Chatvia-Agent-Id identity header.');
        }
        if (! $conversationExternalId) {
            return Response::error('Missing X-Chatvia-Conversation-Id header — leads can only be captured inside an active chat conversation.');
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

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $settings = $agent->settings('leads')->toArray();
        $fieldsConfig = $settings['fields'] ?? [];

        $missing = [];
        foreach (self::FIELDS as $key) {
            $cfg = $fieldsConfig[$key] ?? null;
            if (! is_array($cfg) || empty($cfg['required'])) {
                continue;
            }
            if (empty($validated[$key])) {
                $missing[] = $cfg['title'] ?? $key;
            }
        }

        if ($missing !== []) {
            $list = implode(', ', $missing);

            return Response::error("Cannot capture this lead yet — the following required fields are missing: {$list}. Ask the visitor for these and try again.");
        }

        $existing = ! empty($validated['email'])
            ? $agent->leads()->where('email', $validated['email'])->first()
            : null;

        $payload = [];
        foreach (self::FIELDS as $key) {
            $payload[$key] = $validated[$key] ?? null;
        }

        try {
            if ($existing) {
                // Upsert: merge only the fields the visitor provided this time so
                // we never blank out previously-captured data with nulls.
                $updates = array_filter(
                    $payload,
                    static fn ($value): bool => $value !== null && $value !== '',
                );

                $existing->fill([
                    ...$updates,
                    'chat_conversation_id' => $conversation->id,
                    'ip_address' => $http->ip(),
                    'user_agent' => $http->userAgent(),
                ])->save();

                $lead = $existing;
            } else {
                $lead = $agent->leads()->create([
                    ...$payload,
                    'chat_conversation_id' => $conversation->id,
                    'ip_address' => $http->ip(),
                    'user_agent' => $http->userAgent(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('CaptureLeadTool failed to persist lead', [
                'agent_id' => $agent->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return Response::error($this->captureFailedMessage($agent));
        }

        $successMessage = $settings['success']['message']
            ?? 'Thank you — your details have been received and the team will follow up shortly.';

        return Response::make(Response::text($successMessage))
            ->withStructuredContent([
                'created' => $existing === null,
                'lead_id' => $lead->id,
            ]);
    }

    /**
     * Build the error message returned to the LLM when a lead can't be
     * persisted. We include the agent's own business contact details so the
     * model has real values to fall back on — otherwise it tends to invent
     * generic placeholders like "sales@example.com".
     */
    private function captureFailedMessage(Agent $agent): string
    {
        $business = $agent->settings('business')->toArray();
        $email = $business['email'] ?? null;
        $phone = $business['phone'] ?? null;

        $base = "We couldn't save the visitor's details right now due to a server error.";

        if ($email || $phone) {
            $channels = array_filter([
                $email ? "email {$email}" : null,
                $phone ? "phone {$phone}" : null,
            ]);

            return $base.' Tell the visitor they can reach the team directly via '
                .implode(' or ', $channels)
                .'. Do not invent any other contact details — only share the ones listed here.';
        }

        return $base.' Apologize to the visitor and offer to try again in a moment. Do not invent any contact details (email, phone, etc.) — the business has not configured them.';
    }

    /**
     * Get the tool's input schema.
     *
     * Every field is optional at the schema level so the LLM can attempt
     * with whatever the visitor has shared. Per-agent required-field
     * enforcement happens in `handle()` — that way the same MCP endpoint
     * serves agents with different field configurations without needing
     * per-agent schema generation.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('The visitor\'s full name, exactly as they shared it.'),

            'email' => $schema->string()
                ->description('The visitor\'s email address. Must be a valid email — do not guess or invent one.'),

            'phone' => $schema->string()
                ->description('The visitor\'s phone number (any common format is fine; do not normalize).'),

            'job_title' => $schema->string()
                ->description('The visitor\'s job title or role at their company.'),

            'company' => $schema->string()
                ->description('The visitor\'s company or organisation name.'),

            'location' => $schema->string()
                ->description('The visitor\'s location (city, country, or whatever they shared).'),

            'message' => $schema->string()
                ->description('A short note from the visitor — what they want to discuss, the context of their request, or any extra detail worth passing to the team.'),
        ];
    }
}
