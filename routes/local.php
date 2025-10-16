<?php

use App\Support\PromptLoader;

if (app()->isLocal()) {
    Route::get('playground', function () {
        /** @var \App\Models\Chunk $chunk */
        $chunk = \App\Models\Chunk::find(1);

        dd($chunk->links[0]->chunk);
    });
}
