<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Platform;

/**
 * The headline numbers of the admin panel's dashboard for one window.
 * Workspace counts are only meaningful for the whole platform; for one
 * workspace they are left at zero.
 */
final readonly class PlatformOverviewData
{
    public function __construct(
        /** Visitor messages plus AI replies. */
        public CountTrendData $messages,
        public CountTrendData $conversations,
        /** Visitor sessions first seen in the window. */
        public int $newVisitors,
        /** Average time to an AI reply, in milliseconds; null with no replies. */
        public ?int $avgReplyMs,
        public CountTrendData $newWorkspaces,
        public int $totalWorkspaces,
        /** Workspaces with at least one message in the window. */
        public int $activeWorkspaces,
        public int $newAgents,
        public int $totalAgents,
        /** Forms submitted in chats. */
        public int $leads,
    ) {}
}
