<?php

namespace App\Models;

use App\Collections\AgentCollection;
use App\Data\Chat\LlmModelData;
use App\Enums\AgentFileStatusEnum;
use App\QueryBuilders\AgentQueryBuilder;
use App\Services\Agents\FileAnalyzer;
use App\Traits\HasSettings;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Agent extends Model
{
    use HasFactory, HasSettings;

    protected $guarded = ['id'];

    protected $appends = [
        'url',
        'permanent_url',
        'chat_url',
        'chat_embed_url',
    ];

    /**
     * Get the route key name for Laravel route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the roles that have access to this agent.
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

    public function files(): MorphToMany
    {
        return $this
            ->morphedByMany(SourceFile::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class)
            ->withTimestamps();
    }

    public function websites(): MorphToMany
    {
        return $this->morphedByMany(SourceWebsite::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class)
            ->withTimestamps();
    }

    public function texts(): MorphToMany
    {
        return $this->morphedByMany(SourceText::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class)
            ->withTimestamps();
    }

    public function youtubeVideos(): MorphToMany
    {
        return $this->morphedByMany(SourceYoutubeVideo::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class)
            ->withTimestamps();
    }

    public function starters(): HasMany
    {
        return $this->hasMany(Starter::class);
    }

    public function llms(): BelongsToMany
    {
        return $this->belongsToMany(Llm::class);
    }

    public function llmsFormatted()
    {
        return $this->llms()->get()->map(function (Llm $llm) {
            $data = LlmModelData::fromLlmModel($llm);
            $data->user_allowed_to_use = true;

            return $data;
        });
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class);
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
        return Attribute::get(fn () => $this->url.'/chat');
    }

    public function chatEmbedUrl(): Attribute
    {
        return Attribute::get(fn () => $this->permanent_url.'/chat/embed');
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
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

    public function newCollection(array $models = []): AgentCollection
    {
        return new AgentCollection($models);
    }
}
