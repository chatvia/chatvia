<?php

namespace App\Jobs;

use App\Support\AzureDocumentIntelligence;
use App\Support\OpenRouter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ChunkJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    public function __construct()
    {

    }

    public function handle(): void
    {
        app(AzureDocumentIntelligence::class)->analyzeDocument(
            base_path('llm.pdf'),
            'prebuilt-read',
        );

        $d = app(\App\Support\AzureMarkdownSplitter::class)->splitByHeader(
            file_get_contents(public_path('output.txt'))
        );

        dd($d);

//        $content = file_get_contents(public_path('c1.txt'));
//
//       $res =  app(OpenRouter::class)->chunkWithPages($content);
//
//        file_put_contents(public_path('o.json'), json_encode($res, JSON_PRETTY_PRINT));
    }
}
