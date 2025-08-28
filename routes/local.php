<?php

if (app()->isLocal()) {
    Route::get('playground', function () {
        dd(
            \App\Jobs\Crawler\WebsiteIngestionJob::dispatchSync(
                \App\Models\AgentWebsite::first()),
        );
    });
}
