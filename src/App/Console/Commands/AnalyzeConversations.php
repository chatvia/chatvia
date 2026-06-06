<?php

namespace App\Console\Commands;

use Domain\Analytics\Jobs\AnalyzeConversationJob;
use Domain\Conversation\Models\Conversation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Run the consolidated end-of-life analysis on conversations older than the configured age: one LLM call per conversation sets topic(s), sentiment, tone and language, and records emergent-topic suggestions. Idempotent via analyzed_at.')]
#[Signature('app:analyze-conversations {--chunk=100 : How many conversations to dispatch per batch}')]
class AnalyzeConversations extends Command
{
    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $cutoff = now()->subHours((int) config('chatvia.analytics.analysis.scan_age_hours', 6));
        $dispatched = 0;

        // analyzed_at is set by the queued job (async), so it stays NULL for the
        // duration of this run — chunkById paginates by id and won't loop. The
        // conversations_pending_analysis_idx partial index serves this predicate.
        Conversation::query()
            ->whereNull('analyzed_at')
//            ->where('created_at', '<', $cutoff)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById($chunk, function ($conversations) use (&$dispatched): void {
                foreach ($conversations as $conversation) {
                    AnalyzeConversationJob::dispatch($conversation);
                    $dispatched++;
                }
            });

        $this->info("Dispatched analysis for {$dispatched} conversation(s).");

        return self::SUCCESS;
    }
}
