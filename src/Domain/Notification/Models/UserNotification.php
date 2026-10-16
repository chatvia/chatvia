<?php

declare(strict_types=1);

namespace Domain\Notification\Models;

use App\Identity\Workspace\Models\User;
use Database\Factories\UserNotificationFactory;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Enums\NotificationCategory;
use Domain\Notification\Enums\NotificationKind;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;

/**
 * One entry in a staff member's notification center: a handoff waiting, a
 * source trained, an attack blocked… It belongs to one person only and is
 * read through that person ({@see User::userNotifications()}), so another
 * person's notification is never found. Rows that share a `group_key` gather
 * into one unread row while it is unread; `count` says how many events it
 * holds. Old rows are pruned after the admin's retention days.
 *
 * @property int $id
 * @property string $external_id
 * @property int $user_id
 * @property NotificationKind $kind
 * @property NotificationCategory $category
 * @property NotificationContentData $content
 * @property string|null $group_key
 * @property int $count
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class UserNotification extends Model
{
    /** @use HasFactory<UserNotificationFactory> */
    use HasExternalId, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => NotificationKind::class,
            'category' => NotificationCategory::class,
            'content' => NotificationContentData::class,
            'count' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function unread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Newest activity first: a gathered row moves up when it gathers more.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function latestActivity(Builder $query): Builder
    {
        return $query->orderByDesc('updated_at')->orderByDesc('id');
    }
}
