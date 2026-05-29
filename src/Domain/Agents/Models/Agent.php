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
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Support\Traits\HasSettings;

/**
 * @property int $id
 * @property string $external_id
 * @property int $user_id
 * @property int $team_id
 * @property string $type
 * @property string $name
 * @property string|null $sub_domain
 * @property string|null $domain
 * @property string|null $system_instructions
 * @property int|null $agent_prompt_id
 * @property bool $is_public
 * @property bool $collect_leads
 * @property bool $analytics_enabled
 * @property array<array-key, mixed>|null $metadata
 * @property string $created_via
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgentPrompt|null $agentPrompt
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
 * @property-read Collection<int, Starter> $starters
 * @property-read int|null $starters_count
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
 * @method static AgentQueryBuilder<static>|Agent whereType($value)
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
class Agent extends Model
{
    use HasFactory, HasSettings;

    /**
     * Get the route key name for Laravel route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'external_id';
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
            ->using(AgentSource::class)
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<SourceWebsite, $this, AgentSource>
     */
    public function websites(): MorphToMany
    {
        return $this->morphedByMany(SourceWebsite::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class)
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<SourceText, $this, AgentSource>
     */
    public function texts(): MorphToMany
    {
        return $this->morphedByMany(SourceText::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class)
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<SourceYoutubeVideo, $this, AgentSource>
     */
    public function youtubeVideos(): MorphToMany
    {
        return $this->morphedByMany(SourceYoutubeVideo::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class)
            ->withTimestamps();
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
     * @return HasMany<Starter, $this>
     */
    public function starters(): HasMany
    {
        return $this->hasMany(Starter::class);
    }

    /**
     * @return BelongsToMany<Llm, $this>
     */
    public function llms(): BelongsToMany
    {
        return $this->belongsToMany(Llm::class);
    }

    public function llmsFormatted()
    {
        return $this->llms()->get()->map(function (Llm $llm) {
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

    public function isStandalone(): bool
    {
        return $this->type === 'knowledge_base';
    }

    public function isWidget(): bool
    {
        return $this->type === 'widget';
    }

    public function llmSettings(): array
    {
        $settings = AgentLlm::where('agent_id', $this->id)
            ->select(['temperature', 'top_k', 'top_p', 'frequency_penalty', 'presence_penalty', 'presence_penalty'])
            ->first()
            ?->toArray();

        if (! $settings) {
            return [
                'temperature' => 1.0,
                'top_k' => 0,
                'top_p' => 1.0,
                'presence_penalty' => 0.0,
                'frequency_penalty' => 0.0,
            ];
        }

        return $settings;
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

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'collect_leads' => 'boolean',
            'analytics_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function images(): Collection
    {
        return $this->files()->select('metadata->images as images')->get();
    }

    public function canCollectLeads(): bool
    {
        return $this->collect_leads === true;
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
