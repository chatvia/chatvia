<?php

declare(strict_types=1);

use App\Http\Requests\Auth\RegisterUserRequest;
use Illuminate\Http\Request;
use Support\Http\CloudflareIps;

/**
 * RegisterUserRequest::toData() hands the sign-up form to RegisterUserAction,
 * with the visitor's IP and country taken from the request.
 */
it('builds the registration data with the visitor IP and Cloudflare country', function (): void {
    // An install behind Cloudflare with TRUSTED_PROXIES=cloudflare: the peer
    // is the edge, X-Forwarded-For resolves to the visitor, and CF-IPCountry
    // is believed.
    [$proxies, $headers] = [Request::getTrustedProxies(), Request::getTrustedHeaderSet()];
    Request::setTrustedProxies(CloudflareIps::ranges(), Request::HEADER_X_FORWARDED_FOR);

    try {
        $request = RegisterUserRequest::create('/register', 'POST', [
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'secret-password',
        ], server: [
            'HTTP_CF_IPCOUNTRY' => 'DE',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
            'REMOTE_ADDR' => '172.70.1.1',
        ]);

        $data = $request->toData();

        expect($data->name)->toBe('Ada')
            ->and($data->email)->toBe('ada@example.test')
            ->and($data->password)->toBe('secret-password')
            ->and($data->registered_ip)->toBe('203.0.113.7')
            ->and($data->registered_country_code)->toBe('de');
    } finally {
        Request::setTrustedProxies($proxies, $headers);
    }
});
