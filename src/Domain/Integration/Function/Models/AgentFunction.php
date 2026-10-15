<?php

namespace Domain\Integration\Function\Models;

use App\Identity\Workspace\Models\Workspace;
use Database\Factories\AgentFunctionFactory;
use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Enums\FunctionAuthType;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Tool\Contracts\AttachableToAgents;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
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
 * A webhook-backed tool the workspace defines once (Settings → Functions) and
 * attaches to agents through the `agent_function` pivot. When the model calls
 * the tool, Chatvia sends the arguments to `webhook_url` with the configured
 * verb and credentials (`auth_config`, encrypted at rest).
 *
 * The domain glossary calls this concept a "FunctionTool"; the class keeps its
 * historical name because it is referenced from persisted data.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property string $internal_name
 * @property string|null $display_name
 * @property string $description
 * @property ArrayObject<array-key, mixed> $parameters_schema
 * @property string $webhook_url
 * @property FunctionWebhookMethod $webhook_method
 * @property array<array-key, mixed>|null $webhook_headers
 * @property string $webhook_secret
 * @property FunctionAuthType $auth_type
 * @property array<string, string>|null $auth_config
 * @property int $timeout_ms
 * @property int $max_response_bytes
 * @property bool $requires_approval
 * @property bool $is_enabled
 * @property Vector|null $embedding
 * @property string|null $embedding_bm25
 * @property Carbon|null $last_invoked_at
 * @property string|null $last_error
 * @property int $invocation_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workspace $workspace
 * @property-read Collection<int, Agent> $agents
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereAuthType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereAuthValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereInvocationCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereLastInvokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereMaxResponseBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentFunction whereInternalName($value)
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
#[RouteKey('external_id')]
class AgentFunction extends Model implements AttachableToAgents
{
    /** @use HasFactory<AgentFunctionFactory> */
    use HasExternalId, HasFactory;

    /** How long a webhook call may take when the function sets no timeout. */
    public const int DEFAULT_TIMEOUT_MS = 10_000;

    protected function casts(): array
    {
        return [
            'parameters_schema' => AsArrayObject::class,
            'webhook_method' => FunctionWebhookMethod::class,
            'auth_type' => FunctionAuthType::class,
            'webhook_headers' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'auth_config' => 'encrypted:array',
            'is_enabled' => 'boolean',
            'requires_approval' => 'boolean',
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

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsToMany<Agent, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_function', 'function_id', 'agent_id');
    }

    /**
     * Flip the workspace-wide on/off switch. A disabled function keeps its
     * attachments but is offered to no agent.
     */
    public function toggleEnabled(): void
    {
        $this->update(['is_enabled' => ! $this->is_enabled]);
    }
}
