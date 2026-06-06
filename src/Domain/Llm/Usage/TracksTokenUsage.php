<?php

namespace Domain\Llm\Usage;

/**
 * Convenience for queued jobs that implement {@see AttributesTokenUsage}: registers
 * the {@see TrackTokenUsage} middleware so the job's LLM token usage is attributed
 * to its workspace. Define tokenWorkspaceId() on the using class.
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
