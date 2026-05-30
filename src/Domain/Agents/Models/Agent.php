<?php

namespace Domain\Agents\Models;

use App\Auth\Models\User;
use App\Teams\Models\Role;
use App\Teams\Models\Team;
use Domain\Agents\Collections\AgentCollection;
use Domain\Agents\QueryBuilders\AgentQueryBuilder;
use Domain\Agents\Services\FileAnalyzer;
use Domain\Chat\Data\LlmModelData;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTopic;
use Domain\Sources\Files\Enums\AgentFileStatusEnum;
use Domain\Sources\Files\Models\SourceFile;
use Domain\Sources\Text\Models\SourceText;
use Domain\Sources\Web\Models\SourceWebsite;
use Domain\Sources\Youtube\Models\SourceYoutubeVideo;
use Exception;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\CollectedBy;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Support\Traits\HasSettings;

/**
 * @property int $id
 * @property string $external_id
 * @property int $user_id
 * @property int $team_id
 * @property string $name
 * @property string|null $sub_domain
 * @property string|null $domain
 * @property string|null $system_instructions
 * @property int|null $agent_prompt_id
 * @property int|null $agent_template_id
 * @property bool $is_public
 * @property bool $analytics_enabled
 * @property array<array-key, mixed>|null $metadata
 * @property string $created_via
 * @property string|null $auth_secret
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgentPrompt|null $agentPrompt
 * @property-read AgentTemplate|null $agentTemplate
 * @property-read Collection<int, AgentFunction> $functions
 * @property-read Collection<int, AgentMcpServer> $mcpServers
 * @property-read Collection<int, Llm> $activeLlms
 * @property-read int|null $active_llms_count
 * @property-read AgentSource|null $pivot
 * @property-read Collection<int, SourceFile> $audioFiles
 * @property-read int|null $audio_files_count
 * @property-read string $chat_embed_url
 * @property-read Collection<int, ChatSession> $chatSessions
 * @property-read int|null $chat_sessions_count
 * @property-read string $chat_url
 * @property-read Collection<int, SourceFile> $files
 * @property-read int|null $files_count
 * @property-read Collection<int, AgentFunction> $functions
 * @property-read Collection<int, Lead> $leads
 * @property-read int|null $leads_count
 * @property-read Collection<int, Llm> $llms
 * @property-read int|null $llms_count
 * @property-read Collection<int, AgentMcpServer> $mcpServers
 * @property-read Collection<int, ModelSetting> $modelSettings
 * @property-read int|null $model_settings_count
 * @property-read Collection<int, SourceFile> $pdfFiles
 * @property-read int|null $pdf_files_count
 * @property-read mixed $permanent_url
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read AgentStarter|null $starterSet
 * @property-read Team $team
 * @property-read Collection<int, SourceText> $texts
 * @property-read int|null $texts_count
 * @property-read Collection<int, ChatTopic> $topics
 * @property-read int|null $topics_count
 * @property-read mixed $url
 * @property-read User $user
 * @property-read Collection<int, SourceFile> $videoFiles
 * @property-read int|null $video_files_count
 * @property-read Collection<int, SourceWebsite> $websites
 * @property-read int|null $websites_count
 * @property-read Collection<int, SourceFile> $wordFiles
 * @property-read int|null $word_files_count
 * @property-read Collection<int, SourceYoutubeVideo> $youtubeVideos
 * @property-read int|null $youtube_videos_count
 *
 * @method static AgentCollection<int, static> all($columns = ['*'])
 * @method static AgentQueryBuilder<static>|Agent aggregateStats()
 * @method static AgentQueryBuilder<static>|Agent avgConversationDuration()
 * @method static AgentQueryBuilder<static>|Agent avgFirstResponseTime()
 * @method static AgentQueryBuilder<static>|Agent avgMessagesPerConversation()
 * @method static AgentQueryBuilder<static>|Agent avgResponseTime()
 * @method static AgentQueryBuilder<static>|Agent avgTokensPerMessage()
 * @method static AgentQueryBuilder<static>|Agent conversationsByCountry()
 * @method static AgentQueryBuilder<static>|Agent conversationsByDayOfWeek()
 * @method static AgentQueryBuilder<static>|Agent conversationsByHour()
 * @method static AgentQueryBuilder<static>|Agent conversationsTrend(int $days = 30)
 * @method static AgentQueryBuilder<static>|Agent conversationsWithFeedback()
 * @method static AgentQueryBuilder<static>|Agent countAssistantMessages()
 * @method static AgentQueryBuilder<static>|Agent countConversations()
 * @method static AgentQueryBuilder<static>|Agent dailyMessages(int $days)
 * @method static \Database\Factories\AgentFactory factory($count = null, $state = [])
 * @method static AgentQueryBuilder<static>|Agent firstDataDate()
 * @method static AgentCollection<int, static> get($columns = ['*'])
 * @method static AgentQueryBuilder<static>|Agent messagesByData(\Illuminate\Support\Carbon $startDate, \Illuminate\Support\Carbon $endDate)
 * @method static AgentQueryBuilder<static>|Agent mostAccessedFiles()
 * @method static AgentQueryBuilder<static>|Agent mostUsedModels()
 * @method static AgentQueryBuilder<static>|Agent newModelQuery()
 * @method static AgentQueryBuilder<static>|Agent newQuery()
 * @method static AgentQueryBuilder<static>|Agent peakActivityHour()
 * @method static AgentQueryBuilder<static>|Agent query()
 * @method static AgentQueryBuilder<static>|Agent satisfactionRate()
 * @method static AgentQueryBuilder<static>|Agent topTopics(\Illuminate\Support\Carbon $startDate, \Illuminate\Support\Carbon $endDate, int $limit = 10)
 * @method static AgentQueryBuilder<static>|Agent topicsByDateRange(\Illuminate\Support\Carbon $startDate, \Illuminate\Support\Carbon $endDate)
 * @method static AgentQueryBuilder<static>|Agent totalTokensUsed()
 * @method static AgentQueryBuilder<static>|Agent totalUserMessages()
 * @method static AgentQueryBuilder<static>|Agent uniqueSessionsCount()
 * @method static AgentQueryBuilder<static>|Agent whereCollectLeads($value)
 * @method static AgentQueryBuilder<static>|Agent whereCreatedAt($value)
 * @method static AgentQueryBuilder<static>|Agent whereDomain($value)
 * @method static AgentQueryBuilder<static>|Agent whereExternalId($value)
 * @method static AgentQueryBuilder<static>|Agent whereFunctionsCount($value)
 * @method static AgentQueryBuilder<static>|Agent whereId($value)
 * @method static AgentQueryBuilder<static>|Agent whereIsPublic($value)
 * @method static AgentQueryBuilder<static>|Agent whereMcpServersCount($value)
 * @method static AgentQueryBuilder<static>|Agent whereName($value)
 * @method static AgentQueryBuilder<static>|Agent whereSubDomain($value)
 * @method static AgentQueryBuilder<static>|Agent whereSystemInstructions($value)
 * @method static AgentQueryBuilder<static>|Agent whereTeamId($value)
 * @method static AgentQueryBuilder<static>|Agent whereUpdatedAt($value)
 * @method static AgentQueryBuilder<static>|Agent whereUserId($value)
 * @method static AgentQueryBuilder<static>|Agent withStats()
 * @method static AgentQueryBuilder<static>|Agent withTotalMembers()
 * @method static AgentQueryBuilder<static>|Agent withTotalMessages()
 * @method static AgentQueryBuilder<static>|Agent withUniqueUsers()
 *
 * @mixin \Eloquent
 */
#[Appends([
    'url',
    'permanent_url',
    'chat_url',
    'chat_embed_url',
])]
#[CollectedBy(AgentCollection::class)]
#[Guarded(['id'])]
#[Hidden(['auth_secret'])]
class Agent extends Model
{
    use HasFactory;
    use HasSettings {
        HasSettings::settings as protected baseSettings;
    }

    protected function casts(): array
    {
        return [
            'auth_secret' => 'encrypted',
            'analytics_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * The signing secret for this agent's chat (`?token=`) and widget
     * (`data-auth`) sign-in tokens. Generated lazily on first use; the
     * customer needs it on their backend to mint HS256 JWTs.
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
        $this->auth_secret = 'cvsk_'.Str::random(56);
        $this->save();

        return $this->auth_secret;
    }

    /**
     * Per-request memo for resolvedTemplate() so the read-through methods
     * (settings/llmSettings/resolvedLlms/resolvedStarters/system prompt)
     * resolve the cached template at most once per agent instance.
     */
    protected ?AgentTemplate $cachedTemplate = null;

    protected bool $cachedTemplateLoaded = false;

    /** Per-request memo for resolvedLlms(). */
    protected ?Collection $cachedResolvedLlms = null;

    /**
     * Get the route key name for Laravel route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    /**
     * The reusable account-level template attached to this agent, if any. The
     * raw relation, used by setup-UI/CRUD/detach paths. Runtime read-through
     * goes through resolvedTemplate() (cache-backed) instead.
     *
     * @return BelongsTo<AgentTemplate, $this>
     */
    public function agentTemplate(): BelongsTo
    {
        return $this->belongsTo(AgentTemplate::class);
    }

    /**
     * Cache-backed, per-request-memoized template accessor used by every
     * read-through method. Returns null when no template is attached. The
     * cached payload eager-loads `agentPrompt` so resolveSystemInstructions()
     * costs no extra query. Invalidated by AgentTemplateObserver / the
     * AgentPrompt cache hook.
     */
    public function resolvedTemplate(): ?AgentTemplate
    {
        if (! $this->cachedTemplateLoaded) {
            $this->cachedTemplateLoaded = true;
            $this->cachedTemplate = $this->agent_template_id
                ? Cache::remember(
                    AgentTemplate::cacheKey($this->agent_template_id),
                    now()->addDay(),
                    fn () => AgentTemplate::with('agentPrompt')->find($this->agent_template_id),
                )
                : null;
        }

        return $this->cachedTemplate;
    }

    /**
     * Effective settings for a group. When a template is attached, the
     * per-surface groups (`hosted_chat`, `widget`, `bar`) are read from it
     * (live read-through); shared groups (`leads`, `business`, etc.) stay
     * per-agent.
     */
    public function settings(string $group): \Illuminate\Support\Collection
    {
        if ($this->agent_template_id && in_array($group, ['hosted_chat', 'widget', 'bar'], true)) {
            return $this->resolvedTemplate()->settingsFor($group);
        }

        return $this->baseSettings($group);
    }

    /**
     * Whether the agent loads without a signed token. When a template is
     * attached the flag is read through from it (like every other shared
     * setting), so the template governs access for all agents using it;
     * otherwise it falls back to the agent's own column. Centralising it here
     * means every gate (widget/chat middleware, embed/playground controllers,
     * API resources) inherits the behaviour from a single read.
     */
    protected function isPublic(): Attribute
    {
        return Attribute::get(fn (mixed $value): bool => $this->resolvedTemplate()?->is_public ?? (bool) $value);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Chatters explicitly granted access to this agent via `agent_user`. Only
     * `User`s with `type=chatter` should appear; access for workspace/employee
     * users is resolved through teams + roles, not here.
     *
     * @return BelongsToMany<User, $this>
     */
    public function chatters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agent_user');
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
        return $this->resolvedTemplate()?->agentPrompt?->prompt
            ?? $this->agentPrompt?->prompt
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

    public function addFile(
        UploadedFile $file,
        AgentFileStatusEnum $status = AgentFileStatusEnum::UPLOADED,
        array $options = []): SourceFile
    {
        $path = Storage::putFile(
            path: 'users/'.$this->user_id.'/agents/'.$this->id.'/files',
            file: $file
        );

        try {
            DB::beginTransaction();

            $analyzer = FileAnalyzer::analyze($file);

            $sourceFile = SourceFile::create(array_merge([
                'user_id' => $this->user_id,
                'filename' => $file->getClientOriginalName(),
                'filetype' => get_file_type($file->getMimeType()),
                'filepath' => $path,
                'size_in_kb' => round($file->getSize() / 1024),
                'mime_type' => $file->getClientMimeType(),
                'checksum' => md5_file($file->getPathname()),
                'status' => $status->value,
                'length' => $analyzer->length,
                'metadata' => $analyzer->toArray(),
            ], $options));

            // Attach the file to this agent
            $sourceFile->agents()->attach($this->id);

            DB::commit();

            return $sourceFile;
        } catch (Exception $e) {
            DB::rollBack();

            if (isset($path)) {
                Storage::delete($path);
            }

            throw $e;
        }
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
     * Account-level MCP servers attached to this agent. Presence of the
     * pivot row means "attached"; to pause, detach.
     *
     * @return BelongsToMany<AgentMcpServer, $this>
     */
    public function mcpServers(): BelongsToMany
    {
        return $this->belongsToMany(AgentMcpServer::class, 'agent_mcp_server', 'agent_id', 'mcp_server_id');
    }

    /**
     * One row holding this agent's ordered conversation starters (JSON `text`).
     *
     * @return HasOne<AgentStarter, $this>
     */
    public function starterSet(): HasOne
    {
        return $this->hasOne(AgentStarter::class);
    }

    /**
     * The agent's effective starters as a plain string list. With per-surface
     * storage, the template's starters are now per-surface; this agent-level
     * lookup returns the Hosted Chat surface's starters by default. Surface-
     * aware callers (Widget/Bar controllers) should use
     * `AgentTemplate::startersFor($surface)` directly.
     *
     * @return array<int, string>
     */
    public function resolvedStarters(): array
    {
        if ($this->agent_template_id) {
            return $this->resolvedTemplate()->startersFor('hosted_chat');
        }

        return $this->starterSet?->text ?? [];
    }

    /**
     * @return BelongsToMany<Llm, $this>
     */
    public function llms(): BelongsToMany
    {
        return $this->belongsToMany(Llm::class);
    }

    /**
     * The agent's effective LLMs. With per-surface model storage each surface
     * picks its own model(s); this agent-level lookup returns the Hosted Chat
     * allow-list when a template is attached (the surface that supports
     * multi-model). Memoised per-request so accessing it from multiple places
     * (settings, formatter, router) only hits the DB once.
     *
     * Per-surface lookups (Widget/Bar single model) should call
     * `AgentTemplate::modelsFor($surface)` directly.
     *
     * @return Collection<int, Llm>
     */
    public function resolvedLlms(): Collection
    {
        return $this->cachedResolvedLlms ??= (
            $this->agent_template_id
                ? Llm::whereIn('external_name', $this->resolvedTemplate()->modelsFor('hosted_chat'))->get()
                : $this->llms()->get()
        );
    }

    public function llmsFormatted()
    {
        return $this->resolvedLlms()->map(function (Llm $llm) {
            return LlmModelData::fromLlmModel($llm);
        });
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return BelongsToMany<Llm, $this>
     */
    public function activeLlms(): BelongsToMany
    {
        return $this->llms()->wherePivot('is_active', true);
    }

    public function llmSettings(): array
    {
        if ($this->agent_template_id) {
            return $this->resolvedTemplate()->llmSettingsFor('hosted_chat') ?? $this->defaultLlmSettings();
        }

        $settings = AgentLlm::where('agent_id', $this->id)
            ->select(['temperature', 'top_k', 'top_p', 'frequency_penalty', 'presence_penalty'])
            ->first()
            ?->toArray();

        return $settings ?? $this->defaultLlmSettings();
    }

    /**
     * @return array<string, float|int>
     */
    private function defaultLlmSettings(): array
    {
        return [
            'temperature' => 1.0,
            'top_k' => 0,
            'top_p' => 1.0,
            'presence_penalty' => 0.0,
            'frequency_penalty' => 0.0,
        ];
    }

    public function messageRetentionInDays(): int
    {
        // TODO: Make this configurable as a setting
        return 360;
    }

    public function url(): Attribute
    {
        return Attribute::get(function () {
            $url = parse_url(config('app.url'));

            return $url['scheme'].'://'.$this->sub_domain.'.'.$url['host'];
        });
    }

    public function permanentUrl(): Attribute
    {
        return Attribute::get(function () {
            $url = parse_url(config('app.url'));

            return $url['scheme'].'://'.$this->external_id.'.'.$url['host'];
        });
    }

    public function chatUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->url.'/chat');
    }

    public function chatEmbedUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->permanent_url.'/chat/embed');
    }

    /**
     * @return HasMany<ChatSession, $this>
     */
    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * Image URLs aggregated from this agent's source files' `metadata.images`
     * keys. Postgres JSON path needs the `->` operator at query time, not as
     * an Eloquent column alias — using selectRaw keeps it portable.
     *
     * @return Collection<int, string>
     */
    public function images(): Collection
    {
        return $this->files()
            ->selectRaw("source_files.metadata->'images' AS images")
            ->get()
            ->pluck('images')
            ->filter()
            ->flatMap(fn ($json) => collect(is_string($json) ? json_decode($json, true) : (array) $json)->keys())
            ->unique()
            ->map(fn (string $path): string => Storage::url($path))
            ->values();
    }

    public function wordFiles(): BelongsToMany
    {
        return $this->files()->whereIn('mime_type', config('mimes.word'));
    }

    public function pdfFiles(): BelongsToMany
    {
        return $this->files()->whereIn('mime_type', config('mimes.pdf'));
    }

    public function audioFiles(): BelongsToMany
    {
        return $this->files()->whereIn('mime_type', config('mimes.audio'));
    }

    public function videoFiles(): BelongsToMany
    {
        return $this->files()->whereIn('mime_type', config('mimes.video'));
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(ChatTopic::class, 'agent_topic', 'agent_id', 'chat_topic_id');
    }

    public function newEloquentBuilder($query): AgentQueryBuilder
    {
        return new AgentQueryBuilder($query);
    }
}
