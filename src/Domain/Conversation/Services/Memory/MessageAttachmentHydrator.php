<?php

namespace Domain\Conversation\Services\Memory;

use Domain\Conversation\Data\Attachments\AttachmentData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Form\Models\FormSubmission;

/**
 * Hydrates an in-memory message list (as returned by
 * {@see ChatMemory::getMessagesForConversation()}) with each message's
 * uploaded attachments, so the chat UI can render file chips under the bubble
 * that owns them.
 *
 * Shared by the hosted-chat and widget conversation-history endpoints. The
 * orphan-clustering rule ({@see self::resolveOwner()}) is subtle — it papers
 * over the lag before StoreMessageJob claims `chat_message_id` on a freshly
 * uploaded row — so it lives in exactly one place and is unit-tested directly.
 */
class MessageAttachmentHydrator
{
    /**
     * Attach an `attachments` array to every message and return the augmented
     * list. Assistant turns (and any message with no owned attachments) get an
     * empty array.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    public function hydrate(array $messages, Conversation $conversation): array
    {
        // Overwrite each form artifact's persisted status with the live
        // submission status, so a reload shows a submitted form read-only
        // regardless of whether the on-submit metadata flip landed (it races the
        // async message persist). One query for all forms.
        $this->enrichFormArtifactStatuses($messages);

        $attachments = Attachment::query()
            ->where('chat_conversation_id', $conversation->id)
            ->get();

        // Resolve chat_message_id -> external_id in one query so we don't
        // lazy-load each attachment's message relation.
        $externalIdByMessageId = Message::query()
            ->whereIn('id', $attachments->pluck('chat_message_id')->filter()->unique())
            ->pluck('external_id', 'id')
            ->all();

        [$lastUserExternalId, $lastUserTimestamp] = $this->latestUserMessage($messages);

        $attachmentsByMessageId = $attachments->groupBy(fn (Attachment $attachment): ?string => $this->resolveOwner(
            $attachment->chat_message_id,
            $attachment->created_at?->timestamp,
            $externalIdByMessageId,
            $lastUserExternalId,
            $lastUserTimestamp,
        ));

        foreach ($messages as &$message) {
            $externalId = $message['id'] ?? null;
            $message['attachments'] = $externalId && isset($attachmentsByMessageId[$externalId])
                ? $attachmentsByMessageId[$externalId]->map(fn (Attachment $a): array => AttachmentData::fromModel($a)->toArray())->all()
                : [];
        }
        unset($message);

        return $messages;
    }

    /**
     * Refresh every `form` artifact's `status` from its FormSubmission row
     * (keyed by form_instance_id = the submission external_id). The persisted
     * artifact status can be stale (the on-submit flip races StoreMessageJob),
     * so the DB row is the authoritative source on reload. Batched: one query.
     *
     * @param  array<int, array<string, mixed>>  $messages
     */
    private function enrichFormArtifactStatuses(array &$messages): void
    {
        $instanceIds = [];
        foreach ($messages as $message) {
            foreach (($message['metadata']['artifacts'] ?? []) as $artifact) {
                if (($artifact['kind'] ?? null) === 'form' && isset($artifact['data']['form_instance_id'])) {
                    $instanceIds[] = $artifact['data']['form_instance_id'];
                }
            }
        }

        if ($instanceIds === []) {
            return;
        }

        $statuses = FormSubmission::query()
            ->select(['external_id', 'status'])
            ->whereIn('external_id', array_unique($instanceIds))
            ->get()
            ->mapWithKeys(static fn (FormSubmission $submission): array => [
                // The artifact is sent as JSON, so it carries the plain value.
                $submission->external_id => $submission->status->value,
            ])
            ->all();

        if ($statuses === []) {
            return;
        }

        foreach ($messages as &$message) {
            if (! is_array($message['metadata']['artifacts'] ?? null)) {
                continue;
            }
            foreach ($message['metadata']['artifacts'] as &$artifact) {
                $id = ($artifact['kind'] ?? null) === 'form' ? ($artifact['data']['form_instance_id'] ?? null) : null;
                if ($id !== null && isset($statuses[$id])) {
                    $artifact['data']['status'] = $statuses[$id];
                }
            }
            unset($artifact);
        }
        unset($message);
    }

    /**
     * Decide which message (by external id) an attachment belongs under. Pure
     * decision — unit-tested in isolation.
     *
     * - Claimed rows (chat_message_id set) map to their own message.
     * - Orphans (StoreMessageJob hasn't run yet) cluster onto the latest user
     *   message, BUT only when uploaded at/before it. A newer orphan is a file
     *   still sitting in the composer for the next turn and must not be
     *   retroactively attached.
     *
     * @param  array<int, string>  $externalIdByMessageId  chat_message_id => external_id
     */
    public function resolveOwner(
        ?int $chatMessageId,
        ?int $attachmentCreatedTimestamp,
        array $externalIdByMessageId,
        ?string $lastUserExternalId,
        ?int $lastUserTimestamp,
    ): ?string {
        if ($chatMessageId !== null) {
            return $externalIdByMessageId[$chatMessageId] ?? null;
        }

        if ($lastUserExternalId === null) {
            return null;
        }

        if ($lastUserTimestamp !== null
            && $attachmentCreatedTimestamp !== null
            && $attachmentCreatedTimestamp > $lastUserTimestamp) {
            return null;
        }

        return $lastUserExternalId;
    }

    /**
     * External id + unix timestamp of the most recent user message, or
     * [null, null] when the conversation has no user message yet.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{0: ?string, 1: ?int}
     */
    private function latestUserMessage(array $messages): array
    {
        $externalId = null;
        $timestamp = null;

        foreach ($messages as $message) {
            if (($message['role'] ?? null) === MessageRole::User->value && isset($message['id'])) {
                $externalId = $message['id'];
                // MessageData::$timestamp is unix seconds; coerce defensively
                // in case older cached rows are missing it.
                $timestamp = isset($message['timestamp']) ? (int) $message['timestamp'] : null;
            }
        }

        return [$externalId, $timestamp];
    }
}
