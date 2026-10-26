<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Realtime;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Media\MediaChannelAuthData;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Models\Conversation;
use Domain\Widget\Services\BroadcastAuthorizer;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Support\Str;
use LogicException;
use Pusher\Pusher;

/**
 * Decides whether a hosted-chat visitor may listen on a conversation's
 * private media channel ({@see GeneratedMediaUpdated}), and signs the
 * permission when they may. Visitors may have no login, so the rule is the
 * widget's ({@see BroadcastAuthorizer}): the channel
 * must be `private-chat-media.<conversation>`, and that conversation must
 * belong to the visitor's own session with this agent.
 *
 * The signature comes from the `reverb` broadcast connection, so the keys are
 * the same ones the WebSocket server checks.
 */
final readonly class MediaChannelAuthorizer
{
    /** Echo's prefix for a private channel. */
    private const string PRIVATE_PREFIX = 'private-';

    public function __construct(private BroadcastFactory $broadcasts) {}

    /** The signed permission, or null when the channel is not the visitor's. */
    public function authorize(Agent $agent, string $sessionId, string $channelName, string $socketId): ?MediaChannelAuthData
    {
        $prefix = self::PRIVATE_PREFIX.GeneratedMediaUpdated::channelName('');
        $conversationId = Str::after($channelName, $prefix);

        if (! Str::startsWith($channelName, $prefix) || ! Str::isUuid($conversationId) || ! Str::isUuid($sessionId)) {
            return null;
        }

        $owned = Conversation::query()
            ->inSession($sessionId, $agent->id)
            ->where('external_id', $conversationId)
            ->exists();

        if (! $owned) {
            return null;
        }

        return MediaChannelAuthData::from(json_decode($this->pusher()->authorizeChannel($channelName, $socketId), true, flags: JSON_THROW_ON_ERROR));
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
