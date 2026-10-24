<?php

use App\Identity\Workspace\Enums\DateFormat;
use App\Identity\Workspace\Models\User;

/**
 * The person's date format and language (PreferencesController): "auto" clears
 * the language so the browser's applies, a form without a language keeps the
 * stored one, and unknown values are refused.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('saves the date format and language', function (): void {
    $this->actingAs($this->user)
        ->patch(route('account.preferences.update'), [
            'date_format' => DateFormat::DmySlash->value,
            'locale' => 'ar',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->user->fresh())
        ->date_format->toBe(DateFormat::DmySlash)
        ->locale->toBe('ar');
});

it('stores the language as given, or clears it for "auto"', function (?string $sent, ?string $stored): void {
    $this->user->update(['locale' => 'ar']);

    $this->actingAs($this->user)
        ->patch(route('account.preferences.update'), array_filter([
            'date_format' => DateFormat::DmySlash->value,
            'locale' => $sent,
        ]))
        ->assertSessionHasNoErrors();

    expect($this->user->fresh()->locale)->toBe($stored);
})->with([
    '"auto" clears it' => ['auto', null],
    'a form without a language keeps it' => [null, 'ar'],
]);

it('refuses an invalid preference', function (array $form, string $field): void {
    $this->actingAs($this->user)
        ->patch(route('account.preferences.update'), $form)
        ->assertSessionHasErrors($field);
})->with([
    'no date format' => [[], 'date_format'],
    'an unknown date format' => [['date_format' => 'not-a-format'], 'date_format'],
    'an unsupported language' => [['date_format' => 'dmy_slash', 'locale' => 'not-a-locale'], 'locale'],
]);
