<?php

namespace Domain\Widget\Services;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Data\Auth\WidgetBroadcastAuthData;
use Domain\Widget\Enums\BroadcastDenialReason;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use LogicException;
use Pusher\Pusher;

/**
 * Decides whether a widget visitor may join a conversation's presence channel
 * on the WebSocket server, and signs the permission when they may.
 *
 * Widget visitors have no login, so the rule is:
 *  - the channel must be `presence-chat.<conversation external id>`;
 *  - that conversation must belong to the session the visitor sent;
 *  - when the agent is known, the session must belong to that agent too, so a
 *    channel can never be joined through another agent's widget.
 *
 * The signature comes from the `reverb` broadcast connection, so the keys are
 * the same ones the WebSocket server checks.
 */
readonly class BroadcastAuthorizer
{
    private const string CHANNEL_PATTERN = '/^presence-chat\.(.+)$/';

    /** Member name shown to operators when the visitor gave no name. */
    private const string ANONYMOUS_NAME = 'Visitor';

    public function __construct(private BroadcastFactory $broadcasts) {}

    public function authorize(
        string $channelName,
        string $sessionExternalId,
        string $socketId,
        ?Agent $agent = null,
    ): WidgetBroadcastAuthData|BroadcastDenialReason {
        if (preg_match(self::CHANNEL_PATTERN, $channelName, $matches) !== 1) {
            return BroadcastDenialReason::InvalidChannel;
        }

        $conversation = $this->findConversation($matches[1], $sessionExternalId, $agent);

        if (! $conversation instanceof Conversation) {
            return BroadcastDenialReason::UnknownConversation;
        }

        $payload = $this->pusher()->authorizePresenceChannel(
            $channelName,
            $socketId,
            $sessionExternalId,
            [
                'name' => $conversation->displayName() ?? self::ANONYMOUS_NAME,
                'type' => 'user',
                'session_id' => $sessionExternalId,
            ],
        );

        return WidgetBroadcastAuthData::from(json_decode($payload, true, flags: JSON_THROW_ON_ERROR));
    }

    private function findConversation(string $conversationExternalId, string $sessionExternalId, ?Agent $agent): ?Conversation
    {
        // External ids are UUIDs; anything else can never match.
        if (! Str::isUuid($conversationExternalId) || ! Str::isUuid($sessionExternalId)) {
            return null;
        }

        return Conversation::query()
            ->where('external_id', $conversationExternalId)
            ->whereHas('session', function (Builder $session) use ($sessionExternalId, $agent): void {
                $session->where('external_id', $sessionExternalId);

                if ($agent instanceof Agent) {
                    $session->where('agent_id', $agent->id);
                }
            })
            ->first();
    }

    private function pusher(): Pusher
    {
        $broadcaster = $this->broadcasts->connection('reverb');

        if (! $broadcaster instanceof PusherBroadcaster) {
            throw new LogicException('The `reverb` broadcast connection must use the reverb or pusher driver.');
        }

        return $broadcaster->getPusher();
    }
}
