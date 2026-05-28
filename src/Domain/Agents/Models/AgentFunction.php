<?php

namespace Domain\Agents\Models;

use App\Auth\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Pgvector\Laravel\Vector;
use Support\Concerns\HasExternalId;

/**
 * @property int $id
 * @property string $external_id
 * @property int $user_id
 * @property string $name
 * @property string $description
 * @property ArrayObject<array-key, mixed> $parameters_schema
 * @property string $webhook_url
 * @property string $webhook_method
 * @property array<array-key, mixed>|null $webhook_headers
 * @property string $webhook_secret
 * @property string $auth_type
 * @property array<string, string>|null $auth_config
 * @property int $timeout_ms
 * @property int $max_response_bytes
 * @property bool $enabled
 * @property Vector|null $embedding
 * @property string|null $embedding_bm25
 * @property Carbon|null $last_invoked_at
 * @property string|null $last_error
 * @property int $invocation_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Agent> $agents
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereAuthType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereAuthValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereInvocationCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereLastInvokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereMaxResponseBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereParametersSchema($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereTimeoutMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereWebhookHeaders($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereWebhookMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereWebhookSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereWebhookUrl($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'functions')]
class AgentFunction extends Model
{
    use HasExternalId, HasFactory;

    protected function casts(): array
    {
        return [
            'parameters_schema' => AsArrayObject::class,
            'webhook_headers' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'auth_config' => 'encrypted:array',
            'enabled' => 'boolean',
            'timeout_ms' => 'int',
            'max_response_bytes' => 'int',
            'invocation_count' => 'int',
            'last_invoked_at' => 'datetime',
            'embedding' => Vector::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $function): void {
            if (! $function->webhook_secret) {
                $function->webhook_secret = Str::random(48);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Agent, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_function', 'function_id', 'agent_id');
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }
}
