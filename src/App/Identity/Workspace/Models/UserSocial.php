<?php

namespace App\Identity\Workspace\Models;

use App\Identity\Workspace\Enums\SocialProvider;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a `User` to an external OAuth identity. One row per (user, provider).
 *
 * Kept apart from `users` so one account can link several providers.
 * Look up a user by social identity with `UserSocial::query()
 *   ->where('provider', SocialProvider::Google)
 *   ->where('provider_id', $providerId)->first()?->user`.
 *
 * @property int $id
 * @property int $user_id
 * @property SocialProvider $provider
 * @property string $provider_id
 * @property-read User $user
 */
#[Guarded(['id'])]
class UserSocial extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
