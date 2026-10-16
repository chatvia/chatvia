<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Models;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Enums\WorkspaceRole;
use Database\Factories\WorkspaceFactory;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Membership\Team\Models\Team;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;

/**
 * The tenant: a company account that owns agents, knowledge, tools, chatters,
 * teams and settings. Staff accounts belong to it through `workspace_user`
 * ({@see WorkspaceMembership}); one of them is the owner. Everything a
 * workspace owns is keyed by `workspace_id`, so the owner can change and
 * deleting a person never deletes the business by accident.
 *
 * @property int $id
 * @property string $external_id
 * @property string $name
 * @property bool $spectra_access
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $owner
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Agent> $agents
 * @property-read Collection<int, Chatter> $chatters
 * @property-read MessageLimit|null $messageLimit
 *
 * @method static WorkspaceFactory factory($count = null, $state = [])
 */
#[Guarded(['id'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasExternalId, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'spectra_access' => 'boolean',
        ];
    }

    /**
     * Every staff account of the workspace, owner included.
     *
     * @return BelongsToMany<User, $this, WorkspaceMembership>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->using(WorkspaceMembership::class)
            ->withPivot('role');
    }

    /**
     * The workspace's teammates: every member except the owner.
     *
     * @return BelongsToMany<User, $this, WorkspaceMembership>
     */
    public function teammates(): BelongsToMany
    {
        return $this->members()->wherePivot('role', WorkspaceRole::Member->value);
    }

    /**
     * The one member with the owner role.
     *
     * @return HasOneThrough<User, WorkspaceMembership, $this>
     */
    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, WorkspaceMembership::class, 'workspace_id', 'id', 'id', 'user_id')
            ->where('workspace_user.role', WorkspaceRole::Owner->value);
    }

    /** @return HasMany<Team, $this> */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /** @return HasMany<Agent, $this> */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /** @return HasMany<Chatter, $this> */
    public function chatters(): HasMany
    {
        return $this->hasMany(Chatter::class);
    }

    /** @return HasMany<SourceFile, $this> */
    public function sourceFiles(): HasMany
    {
        return $this->hasMany(SourceFile::class);
    }

    /**
     * Webhook-backed functions; each can be attached to any number of the
     * workspace's agents through `agent_function`.
     *
     * @return HasMany<AgentFunction, $this>
     */
    public function functions(): HasMany
    {
        return $this->hasMany(AgentFunction::class);
    }

    /**
     * Reusable prompts (Settings → Prompts) the workspace's agents can use.
     *
     * @return HasMany<AgentPrompt, $this>
     */
    public function prompts(): HasMany
    {
        return $this->hasMany(AgentPrompt::class);
    }

    /**
     * The workspace's own MCP servers (global built-ins have no workspace).
     *
     * @return HasMany<AgentMcpServer, $this>
     */
    public function mcpServers(): HasMany
    {
        return $this->hasMany(AgentMcpServer::class);
    }

    /**
     * The monthly limit on the workspace's agent replies. Null means no limit.
     *
     * @return HasOne<MessageLimit, $this>
     */
    public function messageLimit(): HasOne
    {
        return $this->hasOne(MessageLimit::class);
    }

    /**
     * Whether any staff account of the workspace is platform staff. Every
     * admin also has a workspace of their own; switching it off would lock
     * the admin out of the admin panel too.
     */
    public function hasPlatformAdmin(): bool
    {
        return $this->members()->where('users.is_admin', true)->exists();
    }

    /**
     * The workspace's security and compliance policy, created on first read
     * so no caller ever sees null.
     */
    public function securityPolicy(): WorkspaceSecurityPolicy
    {
        return WorkspaceSecurityPolicy::forWorkspace($this->id);
    }
}
