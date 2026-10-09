<?php

namespace App\Marketing\Models;

use App\Marketing\Observers\PostObserver;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A post on the public marketing blog (`/blog`). The body is stored both as
 * markdown and as the rendered HTML; the cover image lives on the private
 * disk and is optimised by {@see PostObserver}.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $markdown
 * @property string $html
 * @property string|null $image_path
 * @property string $status
 * @property Carbon|null $published_at
 * @property array<array-key, mixed>|null $meta
 * @property bool $is_featured
 * @property bool $is_popular
 * @property int $views
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $img_url
 *
 * @method static \Database\Factories\PostFactory factory($count = null, $state = [])
 * @method static Builder<static>|Post featured()
 * @method static Builder<static>|Post newModelQuery()
 * @method static Builder<static>|Post newQuery()
 * @method static Builder<static>|Post query()
 * @method static Builder<static>|Post whereCreatedAt($value)
 * @method static Builder<static>|Post whereExcerpt($value)
 * @method static Builder<static>|Post whereIsFeatured($value)
 * @method static Builder<static>|Post whereHtml($value)
 * @method static Builder<static>|Post whereId($value)
 * @method static Builder<static>|Post whereImagePath($value)
 * @method static Builder<static>|Post whereMarkdown($value)
 * @method static Builder<static>|Post whereMeta($value)
 * @method static Builder<static>|Post whereIsPopular($value)
 * @method static Builder<static>|Post wherePublishedAt($value)
 * @method static Builder<static>|Post whereSlug($value)
 * @method static Builder<static>|Post whereStatus($value)
 * @method static Builder<static>|Post whereTitle($value)
 * @method static Builder<static>|Post whereUpdatedAt($value)
 * @method static Builder<static>|Post whereViews($value)
 *
 * @mixin \Eloquent
 */
#[Appends(['img_url'])]
#[Guarded(['id'])]
class Post extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'meta' => 'json',
            'published_at' => 'datetime',
        ];
    }

    public function imgUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path ? Storage::disk('private')->url($this->image_path) : null);
    }

    #[Scope]
    protected function featured(Builder $query): void
    {
        $query
            ->select('id', 'title', 'image_path', 'slug', 'excerpt', 'published_at')
            ->where('is_featured', true);
    }
}
