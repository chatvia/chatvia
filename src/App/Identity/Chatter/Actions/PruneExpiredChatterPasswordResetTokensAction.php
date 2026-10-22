<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Services\ChatterPasswordResetTokens;

/**
 * Deletes chatter password reset tokens that have expired. Chatters have no
 * Laravel password broker (their tokens are keyed by chatter, not email), so
 * `auth:clear-resets` does not reach them; this does the same for their table.
 */
class PruneExpiredChatterPasswordResetTokensAction
{
    public function __construct(
        private readonly ChatterPasswordResetTokens $tokens,
    ) {}

    /**
     * @return int how many tokens were deleted
     */
    public function execute(): int
    {
        return $this->tokens->deleteExpired();
    }
}
