<?php

use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Analytics\Jobs\AnalyzeMessageJob;
use Domain\Conversation\Models\Conversation;

it('puts the analysis jobs on the configured analytics queue', function (): void {
    config(['chatvia.analytics.queue' => 'insights']);

    expect((new AnalyzeMessageJob('msg-1'))->queue)->toBe('insights')
        ->and((new AnalyzeConversationJob(new Conversation))->queue)->toBe('insights');
});
