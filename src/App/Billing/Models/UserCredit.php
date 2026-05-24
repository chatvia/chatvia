<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserCredit extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public $timestamps = false;

    public function usage(): HasOne
    {
        return $this->hasOne(UserUsage::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
