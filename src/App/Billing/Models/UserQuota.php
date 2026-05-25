<?php

namespace App\Billing\Models;

use App\Auth\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $agents_limit
 * @property int $pages_limit
 * @property int $links_limit
 * @property int $minutes_limit
 * @property int $custom_domains_limit
 * @property int $members_limit
 * @property int $teams_limit
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereAgentsLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereCustomDomainsLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereLinksLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereMembersLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereMinutesLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota wherePagesLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereTeamsLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[WithoutTimestamps]
class UserQuota extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
