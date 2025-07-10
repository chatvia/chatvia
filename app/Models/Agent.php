<?php

namespace App\Models;

use App\Enums\AgentFileStatusEnum;
use App\Observers\AgentObserver;
use App\Support\AzureDocumentIntelligence;
use App\Support\Ingestion;
use App\Support\Rag;
use App\Traits\HasSettings;
use Exception;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function isKnowledgeable(): bool
    {
        return $this->type === 'knowledge';
    }
}
