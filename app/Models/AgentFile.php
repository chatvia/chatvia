<?php

namespace App\Models;

use App\Support\Ingestion;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

class AgentFile extends Model
{
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
        $data =  Storage::get($this->path);

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
        return Attribute::get(fn() => Storage::url($this->path));
    }

    public function tempUrl(): string
    {
        return Storage::temporaryUrl($this->path, now()->addMinutes(60));
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function ingestionProgress(): int
    {
        $totalChunks = $this->chunks ?? 0;
        $importedChunks = $this->documents()->count();

        return round($totalChunks > 0 ? ($importedChunks / $totalChunks) * 100 : 0);
    }
}
