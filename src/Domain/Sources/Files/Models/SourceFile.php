<?php

namespace Domain\Sources\Files\Models;

use Domain\Ingestion\Contracts\ChunkableContract;
use Domain\Sources\Files\QueryBuilders\SourceFileQueryBuilder;
use Domain\Ingestion\Services\Ingestion;
use Support\Traits\Chunkable;
use Support\Traits\HasStorageFile;
use Support\Traits\SupportsExternalId;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentSource;

class SourceFile extends Model implements ChunkableContract
{
    use Chunkable, HasFactory, HasStorageFile, SupportsExternalId;

    protected $guarded = ['id'];

    public function newEloquentBuilder($query): SourceFileQueryBuilder
    {
        return new SourceFileQueryBuilder($query);
    }

    public function agents(): MorphToMany
    {
        return $this
            ->morphToMany(Agent::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class)
            ->withTimestamps();
    }

    public function ingest(int $agentId): void
    {
        // If agentId is provided, ingest for that specific agent
        // Otherwise, ingest for all agents this file is attached to
        if ($agentId) {
            app(Ingestion::class)->ingest($this, $agentId);
        } else {
            $this->agents->each(function ($agent) {
                app(Ingestion::class)->ingest($this, $agent->id);
            });
        }
    }

    public function url(): Attribute
    {
        return Attribute::get(fn () => Storage::url($this->filepath));
    }

    public function cancelIngestionJob(): void
    {
        app(Ingestion::class)->cancel($this);
    }

    public function isWordFile(): bool
    {
        return in_array($this->mime_type, config('mimes.word'), true);
    }

    public function isPdfFile(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isAudioFile(): bool
    {
        return in_array($this->mime_type, config('mimes.audio'), true);
    }

    public function isVideoFile(): bool
    {
        return in_array($this->mime_type, config('mimes.video'), true);
    }

    public function download(): StreamedResponse
    {
        return Storage::download($this->filepath);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
        ];
    }

    public function isIngested()
    {
        return $this->status === 'ingested';
    }
}
