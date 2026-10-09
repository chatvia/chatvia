<?php

namespace App\Identity\Workspace\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per (user, sign-in fingerprint). A fingerprint is the SHA-256 of
 * the request IP and User-Agent — stable enough to detect a returning device
 * and not so strict it pages the user on every browser update.
 *
 * Used by RecordKnownDevice to decide whether a Login event is a
 * recognized device or a brand-new one that warrants a sign-in notification.
 *
 * @property int $id
 * @property int $user_id
 * @property string $fingerprint_hash
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon $last_seen_at
 * @property-read User $user
 */
#[Guarded(['id'])]
class UserKnownDevice extends Model
{
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
