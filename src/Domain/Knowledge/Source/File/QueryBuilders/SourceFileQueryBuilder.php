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
    /** @return self<TModel> */
    public function ingested(): self
    {
        return $this->where('source_files.status', SourceStatus::Completed);
    }

    /** @return self<TModel> */
    public function pdf(): self
    {
        return $this->whereIn('source_files.mime_type', config('mimes.pdf'));
    }

    /** @return self<TModel> */
    public function word(): self
    {
        return $this->whereIn('source_files.mime_type', config('mimes.word'));
    }

    /** @return self<TModel> */
    public function markdown(): self
    {
        return $this->whereIn('source_files.mime_type', config('mimes.markdown'));
    }

    /** @return self<TModel> */
    public function audio(): self
    {
        return $this->whereIn('source_files.mime_type', config('mimes.audio'));
    }

    /** @return self<TModel> */
    public function video(): self
    {
        return $this->whereIn('source_files.mime_type', config('mimes.video'));
    }
}
