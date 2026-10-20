<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\ConnectionCheckData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Support\Executable;

/**
 * Checks a PDF maker before it is saved, by making a one-line PDF with the
 * values typed: the only way to know a Cloudflare token may use Browser
 * Rendering, or that a Gotenberg server answers. Without it, a wrong key
 * shows only when someone exports a chat.
 */
final readonly class PdfMakerCheck
{
    private const string TEST_PAGE = '<!doctype html><html><head><meta charset="utf-8"></head><body><p>Chatvia test page</p></body></html>';

    private const int TIMEOUT_SECONDS = 30;

    public function weasyprint(string $binary): ConnectionCheckData
    {
        return Executable::exists($binary)
            ? new ConnectionCheckData(true)
            : new ConnectionCheckData(false, message: 'weasyprint was not found on this server. Install it, or enter its full path under Programs.');
    }

    public function cloudflare(string $accountId, string $token): ConnectionCheckData
    {
        try {
            $response = Http::withToken($token)
                ->timeout(self::TIMEOUT_SECONDS)
                ->post('https://api.cloudflare.com/client/v4/accounts/'.rawurlencode($accountId).'/browser-rendering/pdf', ['html' => self::TEST_PAGE]);
        } catch (ConnectionException) {
            return new ConnectionCheckData(false, message: 'Cloudflare could not be reached.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            return new ConnectionCheckData(false, keyRefused: true, message: 'Cloudflare refused this token. It needs the "Browser Rendering: Edit" permission, for this account.');
        }

        $detail = $response->json('errors.0.message');

        return $this->result($response, 'Cloudflare', is_string($detail) ? $detail : null);
    }

    public function gotenberg(string $url, ?string $username, ?string $password): ConnectionCheckData
    {
        $request = Http::timeout(self::TIMEOUT_SECONDS)->attach('files', self::TEST_PAGE, 'index.html');

        if (filled($username) && filled($password)) {
            $request = $request->withBasicAuth($username, $password);
        }

        try {
            $response = $request->post(rtrim($url, '/').'/forms/chromium/convert/html');
        } catch (ConnectionException) {
            return new ConnectionCheckData(false, message: "Gotenberg could not be reached at {$url}.");
        }

        if ($response->status() === 401) {
            return new ConnectionCheckData(false, keyRefused: true, message: 'Gotenberg refused the username or password.');
        }

        return $this->result($response, 'Gotenberg', null);
    }

    private function result(Response $response, string $name, ?string $detail): ConnectionCheckData
    {
        if (! $response->successful()) {
            return new ConnectionCheckData(false, message: "{$name} could not make a PDF (error {$response->status()})".(filled($detail) ? ": {$detail}" : '.'));
        }

        if (! str_starts_with($response->body(), '%PDF')) {
            return new ConnectionCheckData(false, message: "{$name} answered, but not with a PDF. Check the address.");
        }

        return new ConnectionCheckData(true);
    }
}
