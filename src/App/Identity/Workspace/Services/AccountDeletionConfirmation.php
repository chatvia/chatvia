<?php

namespace App\Identity\Workspace\Services;

use Illuminate\Contracts\Session\Session;

/**
 * The armed state of a self-service account deletion: opening the signed
 * email link arms the deletion for this session for 30 minutes, and only the
 * final explicit click inside that window deletes. The link itself never
 * deletes, so email link scanners can't trigger it.
 */
class AccountDeletionConfirmation
{
    /**
     * Session key holding the unix timestamp at which the email confirmation
     * link was opened.
     */
    private const string CONFIRMED_AT_KEY = 'account_deletion_confirmed_at';

    /** How long (seconds) a confirmed deletion stays armed before it lapses. */
    private const int TTL = 30 * 60;

    public function confirm(Session $session): void
    {
        $session->put(self::CONFIRMED_AT_KEY, now()->timestamp);
    }

    public function isFresh(Session $session): bool
    {
        $confirmedAt = $session->get(self::CONFIRMED_AT_KEY);

        return is_int($confirmedAt) && (now()->timestamp - $confirmedAt) <= self::TTL;
    }
}
