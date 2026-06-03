<?php

namespace Support\Localization\Language\Models;

use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $native
 * @property string $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Conversation> $conversations
 * @property-read int|null $conversations_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereNative($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Language whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'name',
    'native',
    'code',
])]
class Language extends Model
{
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
