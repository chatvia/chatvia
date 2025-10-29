<?php

namespace App\Console\Commands;

use App\Services\AudioCompressor;
use App\Support\OpenAI;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'app:crawl';

    protected $description = 'Command description';

    public function handle(): void
    {
        //        $res = OpenAI::transcribe('/Users/ahmad/Desktop/output.mp3');
        //        dd($res);

        $audio = app(AudioCompressor::class);

        $d = $audio->process('/Users/ahmad/Desktop/audio.mp3');
        dd($d);
    }
}
