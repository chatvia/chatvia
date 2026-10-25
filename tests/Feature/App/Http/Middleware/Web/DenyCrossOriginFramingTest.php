<?php

/**
 * The admin panel runs outside the `web` group, so it gets the always-on
 * headers from DenyCrossOriginFraming: no other site may frame it
 * (clickjacking), whatever the Content Security Policy mode.
 */
it('keeps the admin panel sign-in page from being framed by other sites', function (string $mode): void {
    config(['chatvia.security.csp' => $mode]);

    $this->get('/admin/login')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
})->with(['report-only', 'enforce', 'off']);
