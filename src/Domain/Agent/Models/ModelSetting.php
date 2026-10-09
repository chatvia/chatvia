<?php

namespace Domain\Agent\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One stored setting of a model (an agent, a user): a key and its value inside
 * a settings group. Read through HasSettings, which lays the config defaults
 * underneath, so only values that differ from a default are stored.
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string $group
 * @property string $key
 * @property string $value
 * @property string $data_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $model
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting whereDataType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelSetting whereValue($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class ModelSetting extends Model
{
    /**
     * Get the parent model that owns the setting.
     *
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
