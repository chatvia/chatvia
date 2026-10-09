<?php

namespace Domain\Knowledge\Source\File\QueryBuilders;

use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query scopes for file sources: by ingestion status and by file kind (MIME
 * groups from config/mimes.php).
 *
 * @template TModel of SourceFile
 *
 * @extends Builder<TModel>
 */
class SourceFileQueryBuilder extends Builder
{
    public function ingested(): self
    {
        return $this->where('source_files.status', SourceStatus::Completed);
    }

    public function pdf(): self
    {
        return $this->whereIn('source_files.mime_type', config('mimes.pdf'));
    }

    public function word(): self
    {
        return $this->whereIn('source_files.mime_type', config('mimes.word'));
    }

    public function markdown(): self
    {
        return $this->whereIn('source_files.mime_type', config('mimes.markdown'));
    }

    public function audio(): self
    {
        return $this->whereIn('source_files.mime_type', config('mimes.audio'));
    }

    public function video(): self
    {
        return $this->whereIn('source_files.mime_type', config('mimes.video'));
    }
}
