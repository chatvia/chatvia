<?php

declare(strict_types=1);

namespace App\Http\Chat;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Illuminate\Support\Facades\Auth;

/**
 * Who is signed in to the hosted chat. Chatters sign in on the `chat` guard
 * (the `chatters` table); staff who open an agent's chat from the dashboard
 * sign in on `chat_staff` (the `users` table). Both guards share the /chat
 * session cookie, and at most one of them holds a user at a time.
 *
 * {@see useSignedInGuard()} makes the guard that holds the viewer the default
 * for the request, so `$request->user()` and `Auth::user()` return the viewer.
 */
final class HostedChatViewer
{
    public const string CHATTER_GUARD = 'chat';

    public const string STAFF_GUARD = 'chat_staff';

    /** The signed-in chatter or staff account, or null for a visitor. */
    public static function account(): Chatter|User|null
    {
        $chatter = Auth::guard(self::CHATTER_GUARD)->user();

        if ($chatter instanceof Chatter) {
            return $chatter;
        }

        $staff = Auth::guard(self::STAFF_GUARD)->user();

        return $staff instanceof User ? $staff : null;
    }

    /** The viewer as kind + id, or null for a visitor. */
    public static function data(): ?ChatViewerData
    {
        $account = self::account();

        return match (true) {
            $account instanceof Chatter => ChatViewerData::chatter($account->id),
            $account instanceof User => ChatViewerData::staff($account->id),
            default => null,
        };
    }

    /**
     * Sign the account in on its own guard and sign the other guard out, so
     * the chat never has two viewers at once.
     */
    public static function signIn(Chatter|User $account, bool $remember = false): void
    {
        [$guard, $other] = $account instanceof Chatter
            ? [self::CHATTER_GUARD, self::STAFF_GUARD]
            : [self::STAFF_GUARD, self::CHATTER_GUARD];

        if (Auth::guard($other)->check()) {
            Auth::guard($other)->logout();
        }

        Auth::guard($guard)->login($account, $remember);
        Auth::shouldUse($guard);
    }

    /** Sign out of both chat guards. */
    public static function signOut(): void
    {
        foreach ([self::CHATTER_GUARD, self::STAFF_GUARD] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        Auth::shouldUse(self::CHATTER_GUARD);
    }

    /**
     * Make the guard that holds the viewer the request's default. A chatter
     * wins over staff; with nobody signed in the chatter guard stays default.
     */
    public static function useSignedInGuard(): void
    {
        if (! Auth::guard(self::CHATTER_GUARD)->check() && Auth::guard(self::STAFF_GUARD)->check()) {
            Auth::shouldUse(self::STAFF_GUARD);

            return;
        }

        Auth::shouldUse(self::CHATTER_GUARD);
    }
}
