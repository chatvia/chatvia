<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Services;

use App\Identity\Chatter\Models\Chatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Password reset tokens for chatters, one per chatter. Laravel's own reset
 * tokens are keyed by email, but the same email can be a chatter in several
 * workspaces, so these are keyed by the chatter instead. Only a hash of the
 * token is stored, and a token works for {@see self::EXPIRE_MINUTES} minutes.
 */
final class ChatterPasswordResetTokens
{
    public const int EXPIRE_MINUTES = 60;

    private const string TABLE = 'chatter_password_reset_tokens';

    /** Create a new token for the chatter, replacing any earlier one. */
    public function create(Chatter $chatter): string
    {
        $token = Str::random(64);

        DB::table(self::TABLE)->upsert(
            ['chatter_id' => $chatter->id, 'token' => Hash::make($token), 'created_at' => Carbon::now()],
            ['chatter_id'],
            ['token', 'created_at'],
        );

        return $token;
    }

    public function isValid(Chatter $chatter, string $token): bool
    {
        $row = DB::table(self::TABLE)->where('chatter_id', $chatter->id)->first();

        if ($row === null || $row->created_at === null) {
            return false;
        }

        if (Carbon::parse($row->created_at)->addMinutes(self::EXPIRE_MINUTES)->isPast()) {
            return false;
        }

        return Hash::check($token, (string) $row->token);
    }

    public function delete(Chatter $chatter): void
    {
        DB::table(self::TABLE)->where('chatter_id', $chatter->id)->delete();
    }
}
