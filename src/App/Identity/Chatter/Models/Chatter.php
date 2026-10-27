<?php

namespace App\Identity\Chatter\Models;

use App\Identity\Chatter\Enums\ChatterSource;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Database\Factories\ChatterFactory;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Support\Concerns\HasExternalId;

/**
 * A workspace's end-user: someone who only signs in to the workspace's agents
 * on the hosted chat or the widget, never the dashboard. Chatters belong to
 * exactly one workspace and are kept apart from staff accounts (`users`), so
 * the same email can be a chatter in several workspaces. They sign in on the
 * `chat` guard and reach only the agents granted on `agent_chatter`.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property string $name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property ChatterSource $source
 * @property string|null $locale
 * @property string|null $profile_image_path
 * @property string|null $deactivation_reason
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $avatar
 * @property-read Workspace $workspace
 * @property-read MessageLimit|null $messageLimit
 * @property-read Collection<int, Agent> $agents
 *
 * @method static ChatterFactory factory($count = null, $state = [])
 */
#[Appends(['avatar'])]
#[Guarded(['id'])]
#[Hidden(['password', 'remember_token'])]
class Chatter extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<ChatterFactory> */
    use HasExternalId, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'source' => ChatterSource::class,
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The workspace this chatter belongs to.
     *
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The agents this chatter may chat with.
     *
     * @return BelongsToMany<Agent, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_chatter');
    }

    /**
     * This chatter's monthly limit on their own messages. Null means no limit.
     *
     * @return HasOne<MessageLimit, $this>
     */
    public function messageLimit(): HasOne
    {
        return $this->hasOne(MessageLimit::class);
    }

    /**
     * Whether the chatter may chat with the agent: it must be one of the
     * agents granted to them.
     */
    public function canAccessAgent(Agent $agent): bool
    {
        return $agent->workspace_id === $this->workspace_id
            && $this->agents()->whereKey($agent->id)->exists();
    }

    /**
     * Whether a workspace admin has switched the chatter off.
     */
    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * The locale for this chatter's emails. Null falls back to the app locale.
     */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * The storage prefix for the chatter's files (the profile photo), inside
     * their workspace's: `workspaces/{workspace}/chatters/{external_id}`.
     */
    public function storageRoot(): string
    {
        return $this->workspace->storageRoot().'/chatters/'.$this->external_id;
    }

    /**
     * @return Attribute<string|null, never>
     */
    public function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->profile_image_path
            ? Storage::disk(User::PROFILE_IMAGE_DISK)->url($this->profile_image_path)
            : null);
    }
}
