<?php

namespace Domain\Billing\Models;

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Idempotency ledger for token consumption: one row per assistant turn (unique
 * `turn_key` = assistant message id). A retried
 * {@see \Domain\Billing\Jobs\RecordTokenUsageJob} inserts the same key, hits the
 * unique constraint, and skips the re-charge. Append-only (no updated_at).
 *
 * @property int $id
 * @property int $user_id
 * @property string $turn_key
 * @property int $tokens
 * @property Carbon|null $created_at
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class TokenCharge extends Model
{
    public const UPDATED_AT = null;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
