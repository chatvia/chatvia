<?php

namespace Domain\Llm\Usage;

/**
 * Convenience for queued jobs that implement {@see AttributesTokenUsage}: registers
 * the {@see TrackTokenUsage} middleware so the job's LLM token usage is attributed
 * to its workspace. Define tokenWorkspaceId() on the using class.
 *
 * A class method always wins over a trait method, so a job that defines its own
 * middleware() silently drops this one. Such a job must alias the trait method
 * (`use TracksTokenUsage { middleware as tokenUsageMiddleware; }`) and merge it
 * into its own list.
 */
trait TracksTokenUsage
{
    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new TrackTokenUsage];
    }
}
