<?php

namespace App\Models;

use App\Data\Chat\LlmModelData;
use App\Enums\AgentFileStatusEnum;
use App\Observers\AgentObserver;
use App\Support\Agents\Stats;
use App\Traits\HasSettings;
use Exception;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(AgentObserver::class)]
class Agent extends Model
{
    use HasFactory, HasSettings;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addFile(UploadedFile $file, AgentFileStatusEnum $status = AgentFileStatusEnum::UPLOADED, array $options = []): AgentFile
    {
        try {
            DB::beginTransaction();

            $path = Storage::putFile(path: 'agent/'.$this->id.'/files', file: $file);

            $operationId = null;

            $agentFile = $this->files()->create(array_merge([
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'size_in_kb' => $file->getSize() / 1024,
                'mime_type' => $file->getClientMimeType(),
                'md5_hash' => md5_file($file->getPathname()),
                'status' => $status->value,
                'ingestion_job_id' => $operationId,
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

    public function stats(): Stats
    {
        return app(Stats::class)->setAgent($this->id);
    }

    public function llmSettings(): array
    {
        $settings = AgentLlm::where('agent_id', $this->id)
            ->select(['temperature', 'top_k', 'top_p', 'frequency_penalty', 'presence_penalty', 'presence_penalty'])
            ->first()->toArray();

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
}
