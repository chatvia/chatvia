<?php

namespace Domain\Agent\Models;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Database\Factories\AgentFactory;
use Domain\Agent\Concerns\HasSettings;
use Domain\Agent\Events\AgentToolsChanged;
use Domain\Agent\Observers\AgentObserver;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Agent\QueryBuilders\AgentQueryBuilder;
use Domain\Agent\Services\AgentUrlBuilder;
use Domain\Agent\ValueObjects\TranslatableText;
use Domain\Channel\Concerns\HasMessagingChannels;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\ConversationRetention;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Form\Models\FormSubmission;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Domain\Llm\Enums\ReasoningEffort;
use Domain\Llm\Models\Llm;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Support\Enums\CreatedVia;
use Support\MimeType;

/**
 * An AI agent a workspace builds and deploys: its persona and model, its
 * knowledge sources, the tools it may call, its settings (via `model_settings`)
 * and the surfaces it answers on — Hosted Chat, Chat Widget, Command Bar and
 * the messaging channels ({@see HasMessagingChannels}).
 *
 * Owned by the workspace (`workspace_id`, never the teammate who created it)
 * and placed in one team. `external_id` is the stable public
 * id (route key, permanent URL, storage prefix); `slug` is the friendly,
 * renameable Hosted Chat label. Lifecycle hooks live in {@see AgentObserver}.
 *
 * Deleting is soft ({@see SoftDeletes}): DeleteAgentAction hides the agent at
 * once (route binding, the hosted chat, the widget and every list stop seeing
 * it) and a queued purge removes its data in batches, then the row itself.
 * Code that must still reach a deleted agent during that window (the purge,
 * analytics attribution) asks for it with `withTrashed()`.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property int $team_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $domain
 * @property string|null $system_instructions
 * @property int|null $agent_prompt_id
 * @property int|null $llm_id
 * @property bool $is_public
 * @property array<array-key, mixed>|null $metadata
 * @property CreatedVia $source
 * @property string|null $auth_secret
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read AgentPrompt|null $agentPrompt
 * @property-read Llm|null $llm
 * @property-read AgentSource|null $pivot
 * @property-read Collection<int, SourceFile> $audioFiles
 * @property-read int|null $audio_files_count
 * @property-read string $chat_embed_url
 * @property-read string $chat_url
 * @property-read Collection<int, SourceFile> $files
 * @property-read int|null $files_count
 * @property-read Collection<int, AgentFunction> $functions
 * @property-read Collection<int, FormSubmission> $submissions
 * @property-read int|null $submissions_count
 * @property-read Collection<int, AgentMcpServer> $mcpServers
 * @property-read Collection<int, ModelSetting> $modelSettings
 * @property-read int|null $model_settings_count
 * @property-read Collection<int, SourceFile> $pdfFiles
 * @property-read int|null $pdf_files_count
 * @property-read string $permanent_url
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read AgentStarter|null $starterSet
 * @property-read Team $team
 * @property-read Collection<int, SourceText> $texts
 * @property-read int|null $texts_count
 * @property-read Collection<int, Topic> $topics
 * @property-read int|null $topics_count
 * @property-read string $url
 * @property-read Workspace $workspace
 * @property-read Collection<int, SourceFile> $videoFiles
 * @property-read int|null $video_files_count
 * @property-read Collection<int, SourceWebsite> $websites
 * @property-read int|null $websites_count
 * @property-read Collection<int, SourceFile> $wordFiles
 * @property-read int|null $word_files_count
 * @property-read Collection<int, SourceYoutubeVideo> $youtubeVideos
 * @property-read int|null $youtube_videos_count
 *
 * @method static Collection<int, static> all($columns = ['*'])
 * @method static \Database\Factories\AgentFactory factory($count = null, $state = [])
 * @method static Collection<int, static> get($columns = ['*'])
 * @method static AgentQueryBuilder<static>|Agent newModelQuery()
 * @method static AgentQueryBuilder<static>|Agent newQuery()
 * @method static AgentQueryBuilder<static>|Agent onlyTrashed()
 * @method static AgentQueryBuilder<static>|Agent query()
 * @method static AgentQueryBuilder<static>|Agent whereCollectLeads($value)
 * @method static AgentQueryBuilder<static>|Agent whereCreatedAt($value)
 * @method static AgentQueryBuilder<static>|Agent whereDomain($value)
 * @method static AgentQueryBuilder<static>|Agent whereExternalId($value)
 * @method static AgentQueryBuilder<static>|Agent whereFunctionsCount($value)
 * @method static AgentQueryBuilder<static>|Agent whereId($value)
 * @method static AgentQueryBuilder<static>|Agent whereIsPublic($value)
 * @method static AgentQueryBuilder<static>|Agent whereMcpServersCount($value)
 * @method static AgentQueryBuilder<static>|Agent whereName($value)
 * @method static AgentQueryBuilder<static>|Agent whereSlug($value)
 * @method static AgentQueryBuilder<static>|Agent whereSystemInstructions($value)
 * @method static AgentQueryBuilder<static>|Agent whereTeamId($value)
 * @method static AgentQueryBuilder<static>|Agent whereUpdatedAt($value)
 * @method static AgentQueryBuilder<static>|Agent whereWorkspaceId($value)
 * @method static AgentQueryBuilder<static>|Agent withTrashed(bool $withTrashed = true)
 * @method static AgentQueryBuilder<static>|Agent withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Appends([
    'url',
    'permanent_url',
    'chat_url',
    'chat_embed_url',
])]
#[Guarded(['id'])]
#[Hidden(['auth_secret'])]
#[RouteKey('external_id')]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    use HasMessagingChannels;
    use HasSettings {
        HasSettings::settings as protected baseSettings;
    }
    use SoftDeletes;

    /** Per-instance memo for {@see self::workspaceExternalId()}. */
    protected ?string $resolvedWorkspaceExternalId = null;

    protected function casts(): array
    {
        return [
            'auth_secret' => 'encrypted',
            'metadata' => 'array',
            'llm_id' => 'integer',
            'is_public' => 'boolean',
            'source' => CreatedVia::class,
        ];
    }

    /**
     * The signing secret for this agent's chat (`?token=`) and widget
     * (`data-auth`) sign-in tokens; the customer needs it on their backend to
     * mint HS256 JWTs. Seeded at creation by {@see AgentObserver}, so this is a
     * pure read on every normal path — the lazy generation is only a safety
     * net for a row that has no secret yet. Rotate explicitly via
     * {@see regenerateAuthSecret()}.
     */
    public function ensureAuthSecret(): string
    {
        return $this->auth_secret ?: $this->regenerateAuthSecret();
    }

    /**
     * Mint a fresh signing secret for this agent. Invalidates every chat link
     * or widget token previously signed with the old one.
     */
    public function regenerateAuthSecret(): string
    {
        $this->auth_secret = self::generateAuthSecret();
        $this->save();

        return $this->auth_secret;
    }

    /**
     * A new random signing secret in the agent secret format (`cvsk_` + 56
     * random characters). The single place that defines the format.
     */
    public static function generateAuthSecret(): string
    {
        return 'cvsk_'.Str::random(56);
    }

    /**
     * Effective settings for a group — pulled from the agent's own
     * `model_settings` rows (HasSettings trait), with the config defaults
     * underneath.
     *
     * @return SupportCollection<string, mixed>
     */
    public function settings(string $group): SupportCollection
    {
        return $this->baseSettings($group);
    }

    /**
     * Whether a web surface (Hosted Chat, Widget, Command Bar) is enabled for this
     * agent. Defaults to ON — an agent with no stored `surfaces` setting serves
     * all three. Disabling one hides
     * that surface: Hosted Chat 404s, the Widget/Bar embed doesn't mount.
     */
    public function surfaceEnabled(ConversationSource $surface): bool
    {
        return (bool) ($this->settings('surfaces')->get($surface->value) ?? true);
    }

    /**
     * Whether chatters see the knowledge sources under an answer (Setup →
     * Settings → Replies). Off by default, so an agent with no stored
     * `show_sources` setting keeps its sources on the server.
     */
    public function showsSources(): bool
    {
        return (bool) $this->settings('appearance')->get('show_sources', false);
    }

    /**
     * Whether an optional analytics dimension (`sentiment`, `tone`, `topics`)
     * is actively collecting for this agent. Requires BOTH the chat log (the
     * master switch — with no stored messages there's nothing to analyze) and
     * the dimension's own switch to be on. The dimension switches are stored
     * independently of the chat log, so flipping the chat log back on restores
     * whatever per-dimension preferences were set before. Defaults to on — the
     * `analytics` settings group carries `true` defaults. This is the per-agent
     * half of the gate; the plan-tier capability is the other (see Capability).
     */
    public function analyticsEnabled(string $dimension): bool
    {
        return $this->chatlogEnabled()
            && (bool) $this->settings('analytics')->get($dimension, true);
    }

    /**
     * Whether conversations are persisted for this agent. Off (Setup → Settings
     * → Chat log) stops StoreMessageJob, so nothing reaches the chat log and the
     * analytics dimensions get no data to work with. Defaults to on.
     */
    public function chatlogEnabled(): bool
    {
        return (bool) $this->settings('analytics')->get('chatlog', true);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Chatters granted access to this agent via `agent_chatter`. Staff reach
     * agents through teams and roles, not here.
     *
     * @return BelongsToMany<Chatter, $this>
     */
    public function chatters(): BelongsToMany
    {
        return $this->belongsToMany(Chatter::class, 'agent_chatter');
    }

    /**
     * The reusable library prompt attached to this agent, if any. When set it
     * takes precedence over system_instructions when building the system prompt.
     *
     * @return BelongsTo<AgentPrompt, $this>
     */
    public function agentPrompt(): BelongsTo
    {
        return $this->belongsTo(AgentPrompt::class);
    }

    /**
     * Resolve the effective system prompt for this agent, in precedence order:
     * attached library prompt → the agent's own custom instructions → the
     * built-in default. The default is applied at runtime so the stored
     * `system_instructions` can legitimately be null.
     */
    public function resolveSystemInstructions(): string
    {
        return $this->agentPrompt?->prompt
            ?? $this->system_instructions
            ?? prompt('knowledge/system');
    }

    /**
     * Get the roles that have access to this agent.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * @return MorphToMany<SourceFile, $this, AgentSource>
     */
    public function files(): MorphToMany
    {
        return $this
            ->morphedByMany(SourceFile::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class);
    }

    /**
     * @return MorphToMany<SourceWebsite, $this, AgentSource>
     */
    public function websites(): MorphToMany
    {
        return $this->morphedByMany(SourceWebsite::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class);
    }

    /**
     * @return MorphToMany<SourceText, $this, AgentSource>
     */
    public function texts(): MorphToMany
    {
        return $this->morphedByMany(SourceText::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class);
    }

    /**
     * The agent's curated Q&A entries. Named `qas` so the API route's
     * `{qa}` binding scopes through it.
     *
     * @return HasMany<AgentQuestionAnswer, $this>
     */
    public function qas(): HasMany
    {
        return $this->hasMany(AgentQuestionAnswer::class);
    }

    /**
     * @return MorphToMany<SourceYoutubeVideo, $this, AgentSource>
     */
    public function youtubeVideos(): MorphToMany
    {
        return $this->morphedByMany(SourceYoutubeVideo::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class);
    }

    /**
     * Account-level functions attached to this agent. Presence of the
     * pivot row means "attached"; to pause, detach.
     *
     * @return BelongsToMany<AgentFunction, $this>
     */
    public function functions(): BelongsToMany
    {
        return $this->belongsToMany(AgentFunction::class, 'agent_function', 'agent_id', 'function_id');
    }

    /**
     * Account-scoped ownership check: does this function belong to the same
     * workspace as the agent? Used by the function attach/detach/test
     * controllers to gate "I can only attach functions I own to my agents".
     */
    public function ownsFunction(AgentFunction $function): bool
    {
        return $function->workspace_id === $this->workspace_id;
    }

    /**
     * Attach an account-level function to this agent (idempotent — a repeat
     * attach is a no-op). Single source of truth for the dashboard and the
     * public API; don't re-implement the pivot write inline. Pivot writes fire
     * no Eloquent events, so {@see AgentToolsChanged} tells the tool layer to
     * drop its cached pool.
     */
    public function attachFunction(AgentFunction $function): void
    {
        $this->functions()->syncWithoutDetaching([$function->id]);

        AgentToolsChanged::dispatch($this->id);
    }

    public function detachFunction(AgentFunction $function): void
    {
        $this->functions()->detach($function->id);

        AgentToolsChanged::dispatch($this->id);
    }

    /**
     * Account-level MCP servers attached to this agent. Presence of the
     * pivot row means "attached"; to pause, detach.
     *
     * @return BelongsToMany<AgentMcpServer, $this>
     */
    public function mcpServers(): BelongsToMany
    {
        return $this->belongsToMany(AgentMcpServer::class, 'agent_mcp_server', 'agent_id', 'mcp_server_id')
            ->withPivot('always_available');
    }

    /**
     * Attach a connector (MCP server) to this agent (idempotent). Single source
     * of truth for the dashboard and the public API. Pivot writes fire no
     * Eloquent events, so {@see AgentToolsChanged} tells the tool layer to drop
     * its cached pool.
     */
    public function attachConnector(AgentMcpServer $server): void
    {
        $this->mcpServers()->syncWithoutDetaching([$server->id]);

        AgentToolsChanged::dispatch($this->id);
    }

    public function detachConnector(AgentMcpServer $server): void
    {
        $this->mcpServers()->detach($server->id);

        AgentToolsChanged::dispatch($this->id);
    }

    /**
     * Whether the built-in Forms connector is attached and enabled — gates the
     * inline-form rendering and the captured-leads UI in the dashboard.
     */
    public function isFormsEnabled(): bool
    {
        return $this->mcpServers()->where('slug', 'forms')->where('is_enabled', true)->exists();
    }

    /**
     * One row holding this agent's ordered conversation starters (JSON `starters`).
     *
     * @return HasOne<AgentStarter, $this>
     */
    public function starterSet(): HasOne
    {
        return $this->hasOne(AgentStarter::class);
    }

    /**
     * The agent's effective starters — read from the agent's own ordered starter
     * set. Each entry is `{ text, icon }` (icon = optional Heroicon name or null).
     *
     * @return array<int, array{text: string, icon: string|null}>
     */
    public function resolvedStarters(): array
    {
        return $this->starterSet?->starters ?? [];
    }

    /**
     * Starters resolved for a visitor locale (manual edit, else AI translation,
     * else base text — see {@see TranslatableText}). Returns the lean
     * `{ text, icon }` shape the chat surfaces render (used server-side for the
     * embedded widget; the hosted chat resolves client-side for live switching).
     *
     * @return array<int, array{text: string, icon: string|null}>
     */
    public function resolvedStartersForLocale(?string $locale): array
    {
        return array_map(static fn (array $starter): array => [
            'text' => TranslatableText::fromStored($starter)->in($locale) ?? '',
            'icon' => $starter['icon'] ?? null,
        ], $this->resolvedStarters());
    }

    /**
     * The widget chat-bubble prompts in editor shape — `{ text, translations,
     * overrides }` per bubble — for the Setup editor.
     *
     * @return array<int, array{text: string, translations: array<string, string>, overrides: array<string, string>}>
     */
    public function normalizedBubbles(): array
    {
        return array_values(array_map(static function (mixed $bubble): array {
            $copy = TranslatableText::fromStored($bubble);

            return ['text' => $copy->base ?? '', 'translations' => $copy->translations, 'overrides' => $copy->overrides];
        }, (array) $this->settings('widget')->get('bubbles', [])));
    }

    /**
     * Bubble prompts resolved for a visitor locale (manual edit, else AI
     * translation, else base text). Returns the plain `string[]` the widget
     * renders; empty bubbles are left out.
     *
     * @return array<int, string>
     */
    public function resolvedBubblesForLocale(?string $locale): array
    {
        $resolved = array_map(
            static fn (mixed $bubble): string => TranslatableText::fromStored($bubble)->in($locale) ?? '',
            (array) $this->settings('widget')->get('bubbles', []),
        );

        return array_values(array_filter($resolved, static fn (string $text): bool => $text !== ''));
    }

    /**
     * The UI languages this agent supports (code => display name) — the owner's
     * curated subset of config('chatvia.languages'). An empty/unset selection
     * means "all" (minus the 'auto' sentinel), so existing agents keep every
     * language. Single source of truth for the hosted-chat switcher list, the
     * Setup per-language editors, and which locales get auto-translated.
     *
     * @return array<string, string>
     */
    public function enabledLanguages(): array
    {
        $all = (array) config('chatvia.languages', []);
        unset($all['auto']);

        $codes = array_values(array_filter(
            (array) ($this->settings('appearance')->get('enabled_languages') ?? []),
            filled(...),
        ));

        if ($codes === []) {
            return $all;
        }

        return array_intersect_key($all, array_flip($codes));
    }

    /**
     * The single LLM that answers every chat for this agent. Nullable so a
     * just-created agent without one yet won't break; AgentObserver seeds it
     * to the user's default on create.
     *
     * @return BelongsTo<Llm, $this>
     */
    public function llm(): BelongsTo
    {
        return $this->belongsTo(Llm::class);
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Resolved LLM hyperparameters (temperature/top_k/top_p/penalties). Read
     * from the `llm_settings` settings group, falling back to the config
     * defaults via HasSettings. Stored values can come back as strings
     * ("1"), so each is turned into a number: top-k a whole number, the
     * rest decimals, as the providers' APIs and the Setup form expect.
     *
     * @return array<string, float|int>
     */
    public function llmSettings(): array
    {
        return collect($this->settings('llm_settings')->all())
            ->map(fn (mixed $value, string $key): float|int => $key === 'top_k' ? (int) $value : (float) $value)
            ->all();
    }

    /**
     * How hard the model reasons on this turn (the agent's own model unless
     * another is named): the effort the agent picked, but Off while the admin
     * does not let agents reason or the model does not reason
     * ({@see ReasoningEffort}).
     */
    public function reasoningEffort(?Llm $model = null): ReasoningEffort
    {
        if (! (bool) config('chatvia.ai.reasoning.enabled') || ! (bool) ($model ?? $this->llm)?->reasons()) {
            return ReasoningEffort::Off;
        }

        return ReasoningEffort::tryFrom((string) $this->settings('reasoning')->get('effort')) ?? ReasoningEffort::Off;
    }

    /**
     * How many days this agent's conversations are kept: the install-wide
     * {@see ConversationRetention}. Drives both the Redis TTL in
     * {@see ChatCache::getMessageRetention()} and the
     * `chat_conversations.expires_at` stamped on create.
     */
    public function messageRetentionInDays(): int
    {
        return ConversationRetention::days();
    }

    /**
     * The Hosted Chat address under the agent's renameable slug
     * (`/chat/{slug}`); the same as `chat_url`.
     *
     * @return Attribute<string, never>
     */
    public function url(): Attribute
    {
        return Attribute::get(fn (): string => AgentUrlBuilder::hostedUrl($this));
    }

    /**
     * The Hosted Chat address under the agent's permanent external id
     * (`/chat/{external_id}`).
     *
     * @return Attribute<string, never>
     */
    public function permanentUrl(): Attribute
    {
        return Attribute::get(fn (): string => AgentUrlBuilder::permanentUrl($this));
    }

    /**
     * @return Attribute<string, never>
     */
    public function chatUrl(): Attribute
    {
        return Attribute::get(fn (): string => AgentUrlBuilder::chatUrl($this));
    }

    /**
     * @return Attribute<string, never>
     */
    public function chatEmbedUrl(): Attribute
    {
        return Attribute::get(fn (): string => AgentUrlBuilder::chatEmbedUrl($this));
    }

    /**
     * The entity-first storage prefix of the agent's workspace:
     * `workspaces/{external_id}`. The agent's own files (chat attachments)
     * live under `{storageRoot}/agents/{agent external_id}`; knowledge sources
     * are shared by the workspace's agents, so they live in their own folders
     * under `{storageRoot}/sources/…`.
     */
    public function storageRoot(): string
    {
        return 'workspaces/'.$this->workspaceExternalId();
    }

    /** This agent's own files (attachments, setting images), wiped when it is purged. */
    public function settingsStorageRoot(): string
    {
        return $this->storageRoot().'/agents/'.$this->external_id;
    }

    /**
     * The owning workspace's external id (uuid7) — used as the chat-memory
     * cache tag so a whole workspace's Redis entries can be flushed at once
     * (see {@see ChatCache::workspaceTag()}).
     *
     * Resolved with an explicit relation query rather than `$this->workspace->external_id`:
     * the `workspace` relation is rarely eager-loaded on the chat path, and
     * `Model::shouldBeStrict()` turns a lazy load into an exception outside
     * production. Memoized per instance because {@see ChatCache::tagsFor()}
     * calls this on every cache operation and must not re-query.
     */
    public function workspaceExternalId(): string
    {
        return $this->resolvedWorkspaceExternalId ??= $this->workspace()->value('external_id');
    }

    /** @return MorphToMany<SourceFile, $this, AgentSource> */
    public function wordFiles(): MorphToMany
    {
        return $this->files()->whereIn('mime_type', MimeType::group('word'));
    }

    /** @return MorphToMany<SourceFile, $this, AgentSource> */
    public function pdfFiles(): MorphToMany
    {
        return $this->files()->whereIn('mime_type', MimeType::group('pdf'));
    }

    /** @return MorphToMany<SourceFile, $this, AgentSource> */
    public function audioFiles(): MorphToMany
    {
        return $this->files()->whereIn('mime_type', MimeType::group('audio'));
    }

    /** @return MorphToMany<SourceFile, $this, AgentSource> */
    public function videoFiles(): MorphToMany
    {
        return $this->files()->whereIn('mime_type', MimeType::group('video'));
    }

    /** @return BelongsToMany<Topic, $this> */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'agent_topic');
    }

    /** @return AgentQueryBuilder<self> */
    public function newEloquentBuilder($query): AgentQueryBuilder
    {
        return new AgentQueryBuilder($query);
    }
}
