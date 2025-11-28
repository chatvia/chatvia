<?php

namespace App\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

class SourceFileQueryBuilder extends Builder
{
    public function ingested(): self
    {
        return $this->where('source_files.status', 'ingested');
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
