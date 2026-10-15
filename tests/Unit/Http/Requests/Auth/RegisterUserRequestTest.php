<?php

declare(strict_types=1);

use App\Http\Requests\Auth\RegisterUserRequest;

/**
 * RegisterUserRequest::toData() hands the sign-up form to RegisterUserAction,
 * with the visitor's IP and country taken from the request.
 */
it('builds the registration data with the visitor IP and Cloudflare country', function (): void {
    $request = RegisterUserRequest::create('/register', 'POST', [
        'name' => 'Ada',
        'email' => 'ada@example.test',
        'password' => 'secret-password',
    ], server: ['HTTP_CF_IPCOUNTRY' => 'DE', 'REMOTE_ADDR' => '203.0.113.7']);

    $data = $request->toData();

    expect($data->name)->toBe('Ada')
        ->and($data->email)->toBe('ada@example.test')
        ->and($data->password)->toBe('secret-password')
        ->and($data->registered_ip)->toBe('203.0.113.7')
        ->and($data->registered_country_code)->toBe('de');
});
