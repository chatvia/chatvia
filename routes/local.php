<?php

use App\Jobs\Ingestion\IngestionSummaryJob;

if (app()->isLocal()) {
    Route::get('playground', function () {
        //        \App\Jobs\Ingestion\IngestionJob::dispatchSync(\App\Models\AgentFile::first());

        //        dd(\App\Models\Chunk::find(22)->images);

        //        \App\Support\OpenAI::file();

        //        IngestionSummaryJob::dispatch(\App\Models\AgentFile::find(7));

        //        $lines = file_get_contents(base_path('tests/files/animal-farm-ch1.txt'));

        //        return chunk_split($lines, 1000, "\n");

        $af = \App\Models\AgentFile::first();

        return \App\Support\FileAnalyzer::analyze(base_path('N.pdf'));
    });
}
