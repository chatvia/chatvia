<?php

namespace App\Models;

use App\Data\Chat\LlmModelData;
use App\Enums\AgentFileStatusEnum;
use App\Support\Agents\Stats;
use App\Support\FileAnalyzer;
use App\Traits\HasSettings;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Agent extends Model
{
    use HasFactory, HasSettings;

    protected $guarded = ['id'];

    protected $appends = ['url', 'chat_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addFile(
        UploadedFile $file,
        AgentFileStatusEnum $status = AgentFileStatusEnum::UPLOADED,
        array $options = []): AgentFile
    {
        try {
            DB::beginTransaction();

            $path = Storage::putFile(
                path: 'users/'.$this->user_id.'/agents/'.$this->id.'/files',
                file: $file
            );

            $analyzer = FileAnalyzer::analyze($file);

            $agentFile = $this->files()->create(array_merge([
                'user_id' => $this->user_id,
                'filename' => $file->getClientOriginalName(),
                'filetype' => get_file_type($file->getMimeType()),
                'filepath' => $path,
                'size_in_kb' => round($file->getSize() / 1024),
                'mime_type' => $file->getClientMimeType(),
                'md5_hash' => md5_file($file->getPathname()),
                'status' => $status->value,
                'length' => $analyzer->length,
                'metadata' => $analyzer->toArray(),
            ], $options));

            DB::commit();

            return $agentFile;
        } catch (Exception $e) {
            DB::rollBack();
            Storage::delete($path);
            throw $e;
        }
    }

    public function files(): HasMany
    {
        return $this->hasMany(AgentFile::class);
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

    public function activeLlms(): BelongsToMany
    {
        return $this->llms()->wherePivot('is_active', true);
    }

    public function isKnowledgeable(): bool
    {
        return $this->type === 'knowledge';
    }

    public function isInstruction(): bool
    {
        return $this->type === 'instruction';
    }

    public function stats(): Stats
    {
        return app(Stats::class)->setAgent($this);
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

    public function contextWindowEnabled(): bool
    {
        return $this->settings('message_history')->get('context_window_enabled', true) === true;
    }

    public function contextWindowSize()
    {
        return $this->settings('message_history')->get('context_window_size', -1);
    }

    public function messageRetentionInDays(): int
    {
        return (int) $this->settings('message_history')->get('retention', 30);
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

    public function chatUrl(): Attribute
    {
        return Attribute::get(fn () => $this->url.'/chat');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
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
}
