<?php

use App\Identity\Workspace\Models\User;

it('sends an unverified user from the dashboard to the verification notice', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});
