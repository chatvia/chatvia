<?php

namespace Support\Cms\Changelog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $version
 * @property Carbon $release_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ChangelogItem> $items
 * @property-read int|null $items_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Changelog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Changelog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Changelog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Changelog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Changelog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Changelog whereReleaseDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Changelog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Changelog whereVersion($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'version',
    'release_date',
])]
class Changelog extends Model
{
    public function items(): HasMany
    {
        return $this->hasMany(ChangelogItem::class);
    }

    protected function casts(): array
    {
        return [
            'release_date' => 'datetime',
        ];
    }
}
