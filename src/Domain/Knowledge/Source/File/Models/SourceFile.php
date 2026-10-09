<?php

namespace Domain\Knowledge\Source\File\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Models\AgentSource;
use Domain\Knowledge\Document\Concerns\HasStorageFile;
use Domain\Knowledge\Ingestion\Contracts\ChunkableContract;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Concerns\Chunkable;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\QueryBuilders\SourceFileQueryBuilder;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Support\Concerns\HasExternalId;
use Support\MimeType;

/**
 * A file uploaded as knowledge: a document (PDF, office file, image, text,
 * ebook) or a media file (audio, video). One row per stored file; agents use
 * it through the polymorphic `agent_sources` pivot, so several agents can share
 * one upload. Its chunks hold the searchable content.
 *
 * @property int $id
 * @property string|null $external_id
 * @property int $user_id
 * @property string|null $language
 * @property string|null $document_classification
 * @property string $filename
 * @property string $filetype
 * @property string $filepath
 * @property numeric $size_in_kb
 * @property string $mime_type
 * @property string $checksum
 * @property string|null $parser
 * @property string|null $parser_filepath
 * @property int|null $expected_chunks
 * @property int $length
 * @property string|null $internal_ingestion_job_id
 * @property string|null $internal_chunking_batch_id
 * @property string|null $external_parsing_job_id
 * @property array<array-key, mixed>|null $metadata
 * @property SourceStatus $status
 * @property int $took_in_seconds
 * @property string|null $failure_reason
 * @property int $retrieval_count
 * @property string|null $last_retrieved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgentSource|null $pivot
 * @property-read Collection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read Collection<int, Chunk> $chunks
 * @property-read int|null $chunks_count
 * @property-read Chunk|null $summary
 * @property-read mixed $url
 * @property-read User $user
 *
 * @method static SourceFileQueryBuilder<static>|SourceFile audio()
 * @method static SourceFileQueryBuilder<static>|SourceFile ingested()
 * @method static SourceFileQueryBuilder<static>|SourceFile markdown()
 * @method static SourceFileQueryBuilder<static>|SourceFile newModelQuery()
 * @method static SourceFileQueryBuilder<static>|SourceFile newQuery()
 * @method static SourceFileQueryBuilder<static>|SourceFile pdf()
 * @method static SourceFileQueryBuilder<static>|SourceFile query()
 * @method static SourceFileQueryBuilder<static>|SourceFile video()
 * @method static SourceFileQueryBuilder<static>|SourceFile whereChecksum($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereCreatedAt($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereDocumentClassification($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereExpectedChunks($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereExternalId($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereExternalParsingJobId($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereFailureReason($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereFilename($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereFilepath($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereFiletype($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereId($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereInternalChunkingBatchId($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereInternalIngestionJobId($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereLanguage($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereLastRetrievedAt($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereLength($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereMetadata($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereMimeType($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereParser($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereParserFilepath($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereRetrievalCount($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereSizeInKb($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereStatus($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereTookInSeconds($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereUpdatedAt($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile whereUserId($value)
 * @method static SourceFileQueryBuilder<static>|SourceFile word()
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class SourceFile extends Model implements ChunkableContract
{
    use Chunkable, HasExternalId, HasFactory, HasStorageFile;

    public function newEloquentBuilder($query): SourceFileQueryBuilder
    {
        return new SourceFileQueryBuilder($query);
    }

    public function agents(): MorphToMany
    {
        return $this
            ->morphToMany(Agent::class, 'sourceable', 'agent_sources')
            ->using(AgentSource::class);
    }

    public function url(): Attribute
    {
        return Attribute::get(fn () => Storage::url($this->filepath));
    }

    public function isWordFile(): bool
    {
        return MimeType::isWord($this->mime_type);
    }

    public function isPdfFile(): bool
    {
        return MimeType::isPdf($this->mime_type);
    }

    public function extension(): string
    {
        return strtolower(pathinfo((string) $this->filename, PATHINFO_EXTENSION));
    }

    /**
     * Raw-text formats (.txt, .md) are already chunk-ready, so they're split
     * directly rather than paying the cost/latency of the document parser.
     * PDF, Office, and images go through the parser; ebooks take the Pandoc path below.
     */
    public function isDirectChunkable(): bool
    {
        return in_array($this->extension(), ['txt', 'md', 'markdown'], true);
    }

    /**
     * EPUB / FictionBook are structured text, not scanned documents — Pandoc
     * extracts their markdown directly, so they ride the direct-chunk text
     * pipeline (no document parser, no LibreOffice, no PDF).
     */
    public function isEbook(): bool
    {
        return in_array($this->extension(), config('mimes.ocr.pandoc'), true);
    }

    public function isAudioFile(): bool
    {
        return MimeType::isAudio($this->mime_type);
    }

    public function isVideoFile(): bool
    {
        return MimeType::isVideo($this->mime_type);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'json',
            'status' => SourceStatus::class,
        ];
    }

    public function isIngested(): bool
    {
        return $this->status === SourceStatus::Completed;
    }
}
