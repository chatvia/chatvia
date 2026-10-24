<?php

declare(strict_types=1);

namespace Support\Http;

use Illuminate\Support\Str;
use Illuminate\Support\Uri;

/**
 * Addresses of this app as the outside world reaches it, built on `app.url`
 * (APP_URL) rather than on the request. Behind Docker or a reverse proxy the
 * request's host can be `localhost:8000` or a container name, and an OAuth
 * provider or webhook sender must be given the public address, the same one
 * the app sends when it starts a flow.
 */
final class PublicUrl
{
    /** Internal-only name endings: Docker, mDNS and RFC 6761 names. */
    private const array INTERNAL_SUFFIXES = ['.localhost', '.local', '.internal', '.lan', '.home.arpa'];

    /**
     * The absolute URL of a named route on APP_URL.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function route(string $name, array $parameters = []): string
    {
        return self::base().route($name, $parameters, absolute: false);
    }

    /** APP_URL without a trailing slash. */
    public static function base(): string
    {
        return Str::rtrim((string) config('app.url'), '/');
    }

    /**
     * Whether APP_URL names an address only this machine or its private
     * network can reach (localhost, a container name, a private IP), so an
     * outside provider cannot call back to it.
     */
    public static function isInternal(): bool
    {
        $host = Str::of((string) Uri::of(self::base())->host())->lower()->trim('[]')->value();

        if ($host === '' || $host === 'localhost' || ! Str::contains($host, ['.', ':'])) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        return Str::endsWith($host, self::INTERNAL_SUFFIXES);
    }
}
