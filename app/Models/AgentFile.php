<?php

namespace App\Models;

use App\Support\Ingestion;
use App\Traits\Chunkable;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentFile extends Model
{
    use Chunkable, HasFactory;

    protected $guarded = ['id'];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function readFile(): File
    {
        if (! $this->filepath) {
            throw new Exception("File path is not set for AgentFile ID: {$this->id}");
        }

        $data = Storage::get($this->filepath);

        if ($data === null) {
            throw new Exception("File not found in storage: {$this->filepath}");
        }

        $extension = pathinfo($this->filepath, PATHINFO_EXTENSION);

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
        return Attribute::get(fn () => Storage::url($this->filepath));
    }

    public function tempUrl(): string
    {
        return Storage::temporaryUrl($this->filepath, now()->addMinutes(60));
    }

    public function cancelIngestionJob(): void
    {
        app(Ingestion::class)->cancelJob($this);
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
}
