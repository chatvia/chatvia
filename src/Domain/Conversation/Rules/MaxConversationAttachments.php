<?php

namespace Domain\Conversation\Rules;

use Closure;
use Domain\Conversation\Models\Attachment;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Caps how many files a single conversation can carry. Counts attachments
 * already bound to the conversation (by external id, so callers don't have
 * to resolve the integer PK first) and fails the `file` field with a 422
 * error when the next upload would tip it over `agent.files.upload.max_files`.
 *
 * Null / empty / `pending` conversation ids skip the check — those are
 * pre-conversation uploads on the chatter's Home.vue path, where the
 * composer caps client-side and {@see \Domain\Conversation\Actions\Conversations\CreateConversationAction}
 * slices claimed orphan ids to the same ceiling so a script can't smuggle
 * extras in.
 */
final readonly class MaxConversationAttachments implements ValidationRule
{
    public function __construct(
        private ?string $conversationExternalId,
        private int $max,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (in_array($this->conversationExternalId, [null, '', 'pending'], true)) {
            return;
        }

        $count = Attachment::query()
            ->whereHas('conversation', fn ($q) => $q->where('external_id', $this->conversationExternalId))
            ->count();

        if ($count >= $this->max) {
            $fail("You've reached the limit of {$this->max} files for this chat.");
        }
    }
}
