<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Post extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $appends = ['img_url'];

    protected function casts(): array
    {
        return [
            'meta' => 'json',
            'published_at' => 'datetime',
        ];
    }

    public function imgUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_path ? Storage::disk('scaleway')->url($this->image_path) : null);
    }

    #[Scope]
    protected function featured(Builder $query): void
    {
        $query
            ->select('id', 'title', 'image_path', 'slug', 'excerpt', 'published_at')
            ->where('featured', true)->first();
    }
}
