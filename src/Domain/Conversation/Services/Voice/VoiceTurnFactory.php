<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Data\Voice\VoiceCallData;
use Domain\Conversation\Services\ChatContext;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;

/**
 * Builds the pieces a typed turn has for each turn of a voice call: the
 * request (who is talking, from the call's data), a {@see ChatContext} per
 * spoken turn, and the visitor's words as a {@see UserTurnData}. With them
 * the voice worker reuses the typed turn's recorders unchanged (the reply,
 * the tool audit, the guardrail events, the message limits).
 *
 * The model is the realtime model, as an unsaved catalog entry: it is not a
 * chat model of the catalog, so the stored messages name it but link none.
 */
final class VoiceTurnFactory
{
    public function request(VoiceCallData $call, Agent $agent): StreamRequestData
    {
        return new StreamRequestData(
            agent: $agent,
            message: new StreamRequestMessageData(model: $this->model($call), message: ''),
            externalSessionId: $call->session_id,
            conversationId: $call->conversation_id,
            chatterId: $call->chatter_id,
            chatterName: $call->chatter_name,
            chatterExternalId: $call->chatter_external_id,
            actorUserId: $call->actor_user_id,
            actorName: $call->actor_name,
            saveUserMessageImmediately: true,
            // The transcript lists the sources under a spoken answer, when the
            // agent shows them.
            streamSources: $agent->showsSources(),
            source: $call->source,
            ipAddress: $call->ip_address,
            country: $call->country,
            userAgent: $call->user_agent,
            referrerUrl: $call->referrer_url,
            pageUrl: $call->page_url,
            locale: $call->locale,
            voice: true,
        );
    }

    /** A new spoken turn, for the visitor's speech the provider just committed. */
    public function turn(StreamRequestData $request, string $itemId): VoiceTurn
    {
        $userMessageId = Str::uuid7()->toString();

        return new VoiceTurn($itemId, new ChatContext(
            requestData: $request,
            payload: $this->userTurn($request, $userMessageId, ''),
            llmMessages: [],
            userMessageId: $userMessageId,
            assistantMessageId: Str::uuid7()->toString(),
        ));
    }

    /** The visitor's words in a turn, as a typed message is stored. */
    public function userTurn(StreamRequestData $request, string $messageId, string $text): UserTurnData
    {
        return new UserTurnData(
            external_session_id: $request->externalSessionId,
            external_conversation_id: $request->conversationId,
            external_message_id: $messageId,
            model: $request->message->model->external_name,
            message: $text,
            sent_at: now(),
            message_for_llm: $text,
            chatter_id: $request->chatterId,
            ip_address: $request->ipAddress,
            country: $request->country,
            user_agent: $request->userAgent,
            referrer_url: $request->referrerUrl,
            page_url: $request->pageUrl,
            locale: $request->locale,
            source: $request->source,
        );
    }

    private function model(VoiceCallData $call): Llm
    {
        return new Llm()->forceFill([
            'internal_name' => $call->model,
            'external_name' => $call->model,
        ]);
    }
}
