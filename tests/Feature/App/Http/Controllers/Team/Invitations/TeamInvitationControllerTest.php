<?php

/**
 * Opening a team invitation link (TeamInvitationController@show). The id is a
 * bigint; scanners probe paths like `/invitation/.env`. A missing or
 * non-numeric id goes to the login page before the `signed` middleware runs,
 * never as a Postgres error (a 500).
 */
it('redirects an invitation id that is not one to login', function (string $id): void {
    $this->get('/invitation/'.$id)
        ->assertRedirect(route('login'));
})->with([
    'not a number' => '.env',
    'a number no invitation has' => '999999999',
]);
