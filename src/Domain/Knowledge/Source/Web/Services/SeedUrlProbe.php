<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Web\Services;

use Illuminate\Support\Facades\Http;
use Support\Exceptions\DomainValidationException;
use Support\Exceptions\UnsafeUrlException;
use Support\Http\SafeUrl;
use Throwable;

/**
 * Fetches a website's seed URL once from our own servers to learn where it
 * redirects. This is an SSRF surface: the URL and any redirect target must be
 * public, the client never follows a redirect itself, and the request
 * connects to the exact address that was checked, so a DNS answer that
 * changes between the check and the request cannot reach an internal host.
 */
class SeedUrlProbe
{
    /**
     * The checked redirect target, or null when the URL answers directly.
     *
     * @throws DomainValidationException when the URL is not public or cannot be fetched
     */
    public function redirectOf(string $url): ?string
    {
        $clientOptions = $this->safely(fn (): array => SafeUrl::pinnedClientOptions($url));

        try {
            $response = Http::withOptions($clientOptions)
                ->connectTimeout(10)
                ->timeout(20)
                ->get($url)
                ->throw();
        } catch (Throwable $throwable) {
            report($throwable);
            throw new DomainValidationException('url', __('Failed to fetch the website. Please check the URL and try again.'));
        }

        if (! $response->redirect()) {
            return null;
        }

        $location = $response->header('Location');
        $this->safely(fn () => SafeUrl::assert($location));

        return $location;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $check
     * @return T
     */
    private function safely(callable $check): mixed
    {
        try {
            return $check();
        } catch (UnsafeUrlException $e) {
            throw new DomainValidationException('url', __('This URL is not allowed: :reason', ['reason' => $e->getMessage()]));
        }
    }
}
