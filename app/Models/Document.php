<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pgvector\Laravel\Vector;

class Document extends Model
{
    protected $guarded = ['id'];

    public function casts()
    {
        return [
            'embedding' => Vector::class,
            'keywords' => 'json',
            'metadata' => 'json',
        ];
    }
}
