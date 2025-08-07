<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Pgvector\Laravel\Vector;

class Chunk extends Model
{
    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'embedding' => Vector::class,
            'keywords' => 'json',
            'metadata' => 'json',
        ];
    }

    public function images(): Attribute
    {
        return Attribute::get(function () {
            return collect($this->metadata['images'] ?? [])
                ->keys()
                ->map(fn ($image) => Storage::url($image))
                ->toArray();
        });
    }
}
