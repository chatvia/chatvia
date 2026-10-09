<?php

declare(strict_types=1);

namespace Domain\Llm\Usage;

/**
 * A queued job that consumes LLM tokens (completion / embeddings / rerank) and
 * wants that usage attributed to a workspace for analytics. The {@see TrackTokenUsage}
 * job middleware wraps such a job's handle() in a {@see TokenUsageContext} scope.
 */
interface AttributesTokenUsage
{
    /**
     * The workspace (User) id to attribute this job's token usage to, or null to
     * skip recording (e.g. the owner can no longer be resolved).
     */
    public function tokenWorkspaceId(): ?int;
}
