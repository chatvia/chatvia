<?php

namespace Support\Cms\Changelog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $changelog_id
 * @property string $type
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Changelog $changelog
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangelogItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangelogItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangelogItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangelogItem whereChangelogId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangelogItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangelogItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangelogItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangelogItem whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangelogItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'changelog_id',
    'type',
    'description',
])]
class ChangelogItem extends Model
{
    public function changelog(): BelongsTo
    {
        return $this->belongsTo(Changelog::class);
    }
}
