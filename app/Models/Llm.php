<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Llm extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'features' => 'json',
            'requires_paid_plan' => 'boolean',
        ];
    }
}
