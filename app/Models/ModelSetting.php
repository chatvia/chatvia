<?php

namespace App\Models;

use App\Observers\ModelSettingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[ObservedBy(ModelSettingObserver::class)]
class ModelSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * Get the parent model that owns the setting.
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the setting value with proper type casting.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Set the setting value with proper JSON encoding.
     */
    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
