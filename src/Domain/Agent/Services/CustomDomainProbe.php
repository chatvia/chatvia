<?php

declare(strict_types=1);

namespace Domain\Agent\Services;

use Domain\Agent\Enums\DomainCheck;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;

/**
 * The one place that calls a custom domain: asks it for its check page and
 * tells whether this install answered. HTTPS first, as visitors use it; plain
 * HTTP only to tell "points here, but no certificate yet" from "points
 * elsewhere". The domain is the customer's input, so every call goes through
 * {@see SafeUrl}: no redirects, and no private network addresses.
 */
final readonly class CustomDomainProbe
{
    private const int TIMEOUT_SECONDS = 8;

    public function check(string $host, string $token): DomainCheck
    {
        $path = "/.well-known/chatvia-domain/{$token}";

        try {
            $overHttps = $this->answer("https://{$host}{$path}");

            if ($overHttps === $token) {
                return DomainCheck::Reached;
            }

            $overHttp = $this->answer("http://{$host}{$path}");
        } catch (UnsafeUrlException) {
            return DomainCheck::PrivateAddress;
        }

        return match (true) {
            $overHttp === $token => DomainCheck::NoCertificate,
            $overHttps === null && $overHttp === null => DomainCheck::NotReached,
            default => DomainCheck::PointsElsewhere,
        };
    }

    /**
     * The body of the page, '' for an answer that is not a 200, or null
     * when nothing answered.
     *
     * @throws UnsafeUrlException
     */
    private function answer(string $url): ?string
    {
        $options = SafeUrl::pinnedClientOptions($url);

        $response = rescue(
            fn (): Response => Http::withOptions($options)->timeout(self::TIMEOUT_SECONDS)->get($url),
            report: false,
        );

        if (! $response instanceof Response) {
            return null;
        }

        return $response->successful() ? Str::trim($response->body()) : '';
    }
}
