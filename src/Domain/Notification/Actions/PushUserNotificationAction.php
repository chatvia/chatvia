<?php

declare(strict_types=1);

namespace Domain\Notification\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Data\UserNotificationData;
use Domain\Notification\Events\UserNotificationPushed;
use Domain\Notification\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Puts one notification in a person's inbox and tells their open dashboard
 * tabs about it.
 *
 * A message with a group key gathers into the person's unread row with that
 * key when there is one: the count goes up, the content becomes the newest,
 * and the row moves to the top. Insert-or-gather is one statement on the
 * partial unique index (one unread row per person and key), so two events at
 * once never make two rows. Once the row is read, the next event starts a new
 * one.
 *
 * The row is what counts: if the broadcast fails (Reverb down) it is reported
 * and the bell catches up on the next page load, rather than the caller
 * retrying and counting the event twice.
 */
class PushUserNotificationAction
{
    public function execute(User $user, NotificationMessageData $message): UserNotification
    {
        [$id, $isNew] = $message->groupKey === null
            ? [$this->insert($user, $message), true]
            : $this->gather($user, $message);

        $notification = UserNotification::query()->findOrFail($id);

        try {
            UserNotificationPushed::dispatch($user->id, UserNotificationData::fromModel($notification), $isNew);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $notification;
    }

    private function insert(User $user, NotificationMessageData $message): int
    {
        return UserNotification::query()->create([
            'user_id' => $user->id,
            'kind' => $message->kind,
            'category' => $message->kind->category(),
            'content' => $message->content,
        ])->id;
    }

    /**
     * @return array{0: int, 1: bool} the row id, and whether it is a new row
     */
    private function gather(User $user, NotificationMessageData $message): array
    {
        $now = now();

        $row = DB::selectOne(<<<'SQL'
            INSERT INTO user_notifications (external_id, user_id, kind, category, content, group_key, count, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?::jsonb, ?, 1, ?, ?)
            ON CONFLICT (user_id, group_key) WHERE read_at IS NULL AND group_key IS NOT NULL
            DO UPDATE SET
                count = user_notifications.count + 1,
                kind = EXCLUDED.kind,
                content = EXCLUDED.content,
                updated_at = EXCLUDED.updated_at
            RETURNING id, (xmax = 0) AS inserted
        SQL, [
            (string) Str::uuid7(),
            $user->id,
            $message->kind->value,
            $message->kind->category()->value,
            $message->content->toJson(),
            $message->groupKey,
            $now,
            $now,
        ]);

        return [(int) $row->id, (bool) $row->inserted];
    }
}
