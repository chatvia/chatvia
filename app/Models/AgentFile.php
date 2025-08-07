<?php

namespace App\Models;

use App\Support\Ingestion;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentFile extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function filename(): Attribute
    {
        return Attribute::get(fn () => basename($this->path));
    }

    public function readFile(): File
    {
        $data = Storage::get($this->path);

        $extension = pathinfo($this->path, PATHINFO_EXTENSION);

        $tempPath = tempnam(sys_get_temp_dir(), 'agent_file_').'.'.$extension;

        file_put_contents($tempPath, $data);

        return new File($tempPath);
    }

    public function ingest(): void
    {
        app(Ingestion::class)->ingest($this);
    }

    public function url(): Attribute
    {
        return Attribute::get(fn () => Storage::url($this->path));
    }

    public function tempUrl(): string
    {
        return Storage::temporaryUrl($this->path, now()->addMinutes(60));
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    public function ingestionProgress(): int
    {
        $totalChunks = $this->chunks ?? 0;
        $importedChunks = $this->documents()->count();

        return round($totalChunks > 0 ? ($importedChunks / $totalChunks) * 100 : 0);
    }

    public function cancelIngestionJob(): array
    {
        return app(Ingestion::class)->cancelJob($this);
    }

    public function isWordFile(): bool
    {
        $wordMimeTypes = [
            'application/msword',                                                                 // .doc
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',          // .docx
        ];

        return in_array($this->mime_type, $wordMimeTypes, true);
    }

    public function isPdfFile(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isAudioFile(): bool
    {
        $audioMimeTypes = [
            'audio/mpeg',           // .mp3
            'audio/mp4',            // .m4a
            'audio/wav',            // .wav
            'audio/wave',           // .wav (alternative)
            'audio/x-wav',          // .wav (alternative)
            'audio/ogg',            // .ogg
            'audio/webm',           // .webm
            'audio/flac',           // .flac
            'audio/aac',            // .aac
            'audio/x-ms-wma',       // .wma
            'audio/amr',            // .amr
            'audio/3gpp',           // .3gp
            'audio/x-m4a',          // .m4a (alternative)
        ];

        return in_array($this->mime_type, $audioMimeTypes, true);
    }

    public function convertToPdf(): ?File
    {
        $file = $this->readFile();

        if ($this->isPdfFile()) {
            return $file;
        }

        return new File(office()->convert($file->getPathname(), 'pdf'));
    }

    public function download(): StreamedResponse
    {
        return Storage::download($this->path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(Chunk::class)->where('chunk_index', 0)->latestOfMany();
    }

    public function images()
    {
//        return $this->chunks()
//            ->whereRaw("jsonb_array_length(metadata->'images') > 0")
//            ->get();
        return $this->chunks()
            ->select('metadata')
            ->get()
            ->reject(fn($item) => count($item['metadata']['images'] ?? []) === 0)
            ->pluck('metadata.images')
            ->map(fn($images) => collect($images)->keys()->map(fn($image) => Storage::url($image))->toArray())
            ->flatten()
            ->toArray();
    }
}
