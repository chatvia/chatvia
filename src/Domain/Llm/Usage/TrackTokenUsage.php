<?php

namespace Domain\Llm\Usage;

use Closure;

/**
 * Job middleware: opens a {@see TokenUsageContext} scope around the job's handle()
 * so every LLM call it makes (completion / embeddings / rerank) accumulates into a
 * single per-job token total, flushed once when the job finishes.
 *
 * Attach via the {@see TracksTokenUsage} trait; the job must implement
 * {@see AttributesTokenUsage} to declare its owner. A job that isn't an
 * AttributesTokenUsage passes straight through.
 */
class TrackTokenUsage
{
    public function handle(object $job, Closure $next): mixed
    {
        if ($job instanceof AttributesTokenUsage) {
            return app(TokenUsageContext::class)->track(
                $job->tokenWorkspaceId(),
                fn () => $next($job),
            );
        }

        return $next($job);
    }
}
