<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentFunction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'parameters_schema' => AsArrayObject::class,
            'webhook_headers' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'auth_value' => 'encrypted',
            'enabled' => 'boolean',
            'timeout_ms' => 'int',
            'max_response_bytes' => 'int',
            'invocation_count' => 'int',
            'last_invoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $function) {
            if (! $function->external_id) {
                $function->external_id = (string) Str::uuid();
            }
            if (! $function->webhook_secret) {
                $function->webhook_secret = Str::random(48);
            }
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }
}
