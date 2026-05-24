<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModelSetting extends Model
{
    protected $guarded = ['id'];

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
