<?php

namespace App\Http\Controllers\Widget;

use Domain\Chat\Data\ChatOutputPayload;
use Domain\Chat\Models\ChatConversation;
use Domain\Agents\Models\Llm;
use Domain\Chat\Services\Memory\ChatMemory;
use App\Widget\WidgetVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WidgetLeadCollectController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, ChatMemory $memory)
    {
        $agentId = $request->header('X-AGENT-ID');
        $threadId = $request->header('X-THREAD-ID');
        $sessionId = $request->header('X-SESSION-ID');

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
            $id = ChatConversation::where('external_id', $threadId)->value('id');
            $agent->leads()->create(
                array_merge($validated, [
                    'chat_conversation_id' => $id,
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
                ->setExternalSessionId($sessionId)
                ->setConversationId($threadId)
                ->addMessageViaPayload(
                    new ChatOutputPayload(
                        external_session_id: $sessionId,
                        external_conversation_id: $threadId,
                        external_message_id: Str::uuid()->toString(),
                        message: 'We have received your contact information. We will get back to you shortly. Thank you!',
                        model: $llm->internal_name,
                        sent_at: now(),
                    )
                );
        }

        return response(status: 201);
    }
}
