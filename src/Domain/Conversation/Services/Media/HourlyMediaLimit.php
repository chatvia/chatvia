<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Media;

use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The per-chat hourly cap on pictures, audio files and videos (Settings → AI
 * models), each kind counted apart. A slot is taken when a file is asked for
 * and given back when making it fails, so a chatter who retries a failed
 * file is not turned away. Kept in the cache rather than counted from rows:
 * deleting a reply deletes its file, and that must not free a slot.
 */
class HourlyMediaLimit
{
    /** One hour, the window of the limit. */
    private const int HOUR = 3600;

    public function isReached(Conversation $conversation, GeneratedMediaKind $kind): bool
    {
        return RateLimiter::tooManyAttempts($this->key($conversation->id, $kind), $this->perHour($kind));
    }

    public function take(Conversation $conversation, GeneratedMediaKind $kind): void
    {
        RateLimiter::hit($this->key($conversation->id, $kind), self::HOUR);
    }

    /**
     * Gives back the slot of a file that could not be made. A file asked for
     * more than an hour ago left the window already, and a window that has
     * restarted since holds nothing of it.
     */
    public function giveBack(GeneratedMedia $media): void
    {
        $key = $this->key($media->chat_conversation_id, $media->kind);

        if ($media->created_at?->lessThan(now()->subSeconds(self::HOUR)) ?? true) {
            return;
        }

        if ((int) RateLimiter::attempts($key) > 0) {
            RateLimiter::decrement($key, self::HOUR);
        }
    }

    private function perHour(GeneratedMediaKind $kind): int
    {
        return max(1, (int) config(match ($kind) {
            GeneratedMediaKind::Image => 'chatvia.ai.images_per_chat_per_hour',
            GeneratedMediaKind::Audio => 'chatvia.ai.audio_per_chat_per_hour',
            GeneratedMediaKind::Video => 'chatvia.ai.videos_per_chat_per_hour',
        }));
    }

    private function key(int $conversationId, GeneratedMediaKind $kind): string
    {
        return "generated-media:{$kind->value}:{$conversationId}";
    }
}
