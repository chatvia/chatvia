<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $agent_id
 * @property string $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Starter newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Starter newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Starter query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Starter whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Starter whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Starter whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Starter whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Starter whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class Starter extends Model {}
