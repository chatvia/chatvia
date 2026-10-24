<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Opening the emailed confirmation link (ConfirmAccountDeletionController):
 * a valid signed link for the signed-in owner arms the session for the final
 * click; an unsigned link, or one minted for someone else, is refused.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
});

function cadLink(User $user): string
{
    return URL::temporarySignedRoute('account.delete.confirm', now()->addMinutes(30), ['userId' => $user->id]);
}

it('arms the session and goes back to the delete page when the signed link is opened', function (): void {
    $this->actingAs($this->owner)
        ->get(cadLink($this->owner))
        ->assertRedirect(route('account.delete'))
        ->assertSessionHas('account_deletion_confirmed_at');
});

it('refuses a link that is not signed for the signed-in owner', function (string $link): void {
    $url = $link === 'unsigned'
        ? route('account.delete.confirm', ['userId' => $this->owner->id])
        : cadLink(User::factory()->create());

    $this->actingAs($this->owner)
        ->get($url)
        ->assertForbidden()
        ->assertSessionMissing('account_deletion_confirmed_at');
})->with([
    'without a signature' => 'unsigned',
    // A valid signature, but for someone else's id.
    'signed for another account' => 'other',
]);
