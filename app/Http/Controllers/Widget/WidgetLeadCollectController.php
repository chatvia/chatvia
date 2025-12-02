<?php

namespace App\Http\Controllers\Widget;

use App\Data\Chat\ChatOutputPayload;
use App\Models\Llm;
use App\Services\Memory\ChatMemory;
use App\Services\Widget\WidgetVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WidgetLeadCollectController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatMemory $memory)
    {
        $agentId = $request->header('x-agent-id');
        $threadId = $request->header('x-thread-id');
        $sessionId = $request->header('x-session-id');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        $agent = current_agent_from_widget();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:255'],
            'job_title' => ['sometimes', 'string', 'max:255'],
            'company' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
            'message' => ['sometimes', 'string'],
            'referrer_url' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:255'],
            'user_agent' => ['sometimes', 'string', 'max:255'],
        ]);

        if (! $agent->leads()->where('email', $validated['email'])->exists()) {
            $agent->leads()->create(
                array_merge($validated, [
                    'chat_conversation_id' => $threadId,
                    'ip_address' => $request->ip(),
                    'referrer_url' => $request->input('referrer'),
                    'locale' => $request->input('locale'),
                    'page_url' => $request->input('page_url'),
                ])
            );

            /** @var Llm $llm */
            $llm = $agent->llms()->first();

            $memory
                ->setAgent($agent)
                ->setSessionId($sessionId)
                ->setConversationId($threadId)
                ->addMessageViaPayload(
                    new ChatOutputPayload(
                        session_id: $sessionId,
                        conversation_id: $threadId,
                        message_id: Str::ulid()->toString(),
                        message: 'We have received your contact information. We will get back to you shortly. Thank you!',
                        model: $llm->internal_name,
                        sent_at: now(),
                    )
                );
        }

        return response(status: 201);
    }
}
