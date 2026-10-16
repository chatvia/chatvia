<?php

declare(strict_types=1);

namespace Domain\Security\Services;

/**
 * The fingerprint of an anonymous visitor: a keyed hash of their network
 * address and browser, made on the server (no script runs on the visitor's
 * device, so no consent is needed). It is per workspace, so the same visitor
 * has unrelated keys in two workspaces, and it cannot be turned back into the
 * address.
 *
 * An IPv6 address counts by its /64 network: devices change the rest of it
 * often for privacy. People on one network with the very same browser share a
 * fingerprint, and a new network or browser gives a new one; it stops casual
 * repeat abuse, not a determined attacker.
 */
final class VisitorFingerprint
{
    /** The fingerprint, or null without an address (the messaging channels). */
    public function for(int $workspaceId, ?string $ip, ?string $userAgent): ?string
    {
        $network = $this->network($ip);

        if ($network === null) {
            return null;
        }

        return hash_hmac('sha256', 'visitor|'.$workspaceId.'|'.$network.'|'.trim((string) $userAgent), (string) config('app.key'));
    }

    private function network(?string $ip): ?string
    {
        $ip = filter_var(trim((string) $ip), FILTER_VALIDATE_IP);
        $packed = $ip === false ? false : inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        // IPv6: keep the first 64 bits. IPv4: the whole address.
        return strlen($packed) === 16
            ? (string) inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64'
            : (string) inet_ntop($packed);
    }
}
