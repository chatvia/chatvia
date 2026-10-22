<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Sessions;

use App\Identity\Workspace\Data\Sessions\DeviceSightingData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserKnownDevice;

/**
 * Remembers the device a staff account signed in from: one row per (user,
 * fingerprint), where the fingerprint is the SHA-256 of the IP and the
 * User-Agent. A known device gets its last_seen_at moved on.
 *
 * One `INSERT … ON CONFLICT DO UPDATE`, so two sign-ins at the same moment
 * from the same device cannot both insert and break the unique index (which
 * would fail the sign-in). `xmax = 0` on the returned row tells an insert
 * from an update.
 */
class RecordKnownDeviceAction
{
    public function execute(User $user, ?string $ip, ?string $userAgent): DeviceSightingData
    {
        $fingerprint = hash('sha256', ($ip ?? '').'|'.($userAgent ?? ''));
        $now = now();

        $row = $user->getConnection()->selectOne(<<<'SQL'
            INSERT INTO user_known_devices (user_id, fingerprint_hash, ip, user_agent, last_seen_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (user_id, fingerprint_hash) DO UPDATE SET
                last_seen_at = EXCLUDED.last_seen_at,
                ip = EXCLUDED.ip,
                user_agent = EXCLUDED.user_agent,
                updated_at = EXCLUDED.updated_at
            RETURNING (xmax = 0) AS inserted
        SQL, [$user->id, $fingerprint, $ip, $userAgent, $now, $now, $now]);

        $isNew = (bool) $row?->inserted;

        return new DeviceSightingData(
            isNew: $isNew,
            // Only a new device needs the answer, for the new-device email.
            hasOtherDevices: $isNew && UserKnownDevice::query()
                ->where('user_id', $user->id)
                ->where('fingerprint_hash', '!=', $fingerprint)
                ->exists(),
        );
    }
}
