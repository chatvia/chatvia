<?php

namespace App\Console\Commands;

use Domain\Analytics\Actions\Analysis\QueuePendingConversationAnalysisAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Scheduled pass that queues the end-of-life analysis of finished
 * conversations (see {@see QueuePendingConversationAnalysisAction}).
 */
#[Description('Run the consolidated end-of-life analysis on conversations older than the configured age: one LLM call per conversation sets topic(s), sentiment, tone and language, and records emergent-topic suggestions. Idempotent via analyzed_at.')]
#[Signature('app:analyze-conversations {--chunk=100 : How many conversations to dispatch per batch}')]
class AnalyzeConversations extends Command
{
    public function handle(QueuePendingConversationAnalysisAction $queueAnalysis): int
    {
        $queued = $queueAnalysis->execute((int) $this->option('chunk'));

        $this->info("Dispatched analysis for {$queued} conversation(s).");

        return self::SUCCESS;
    }
}
