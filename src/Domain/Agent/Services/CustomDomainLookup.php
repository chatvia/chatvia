<?php

declare(strict_types=1);

namespace Domain\Agent\Services;

use Domain\Agent\Models\AgentDomain;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Support\License\Entitlements;

/**
 * Answers which agent a custom domain serves, and which domain an agent's
 * links use, without a query per request: the saved domains are cached
 * together (memoized within a request, as every agent's URLs ask), and
 * {@see AgentDomain} drops the cache whenever one changes.
 *
 * Every answer is "none" while custom domains are unavailable: without a
 * license key, or until an admin turns them on (Settings → Platform). Saved
 * domains then stop serving and links fall back to APP_URL, and they come
 * back as they were when the feature does.
 */
final readonly class CustomDomainLookup
{
    private const string CACHE_KEY = 'agent-domains';

    private const int CACHE_TTL_MINUTES = 10;

    public function __construct(private Entitlements $entitlements) {}

    /** Whether custom domains work on this install: a license key and the admin switch. */
    public function isAvailable(): bool
    {
        return $this->entitlements->customDomains() && config('chatvia.custom_domains.enabled') === true;
    }

    /** What a custom domain's CNAME record points to: this install's own host. */
    public function cnameTarget(): string
    {
        return Str::lower((string) Uri::of((string) config('app.url'))->host());
    }

    /** The verified domain that serves the agent's Hosted Chat, if any. */
    public function activeHostFor(int $agentId): ?string
    {
        foreach ($this->domains() as $host => $domain) {
            if ($domain['agent_id'] === $agentId && $domain['verified']) {
                return $host;
            }
        }

        return null;
    }

    /** The agent a verified domain serves, if any. */
    public function activeAgentIdFor(string $host): ?int
    {
        $domain = $this->domains()[$this->normalize($host)] ?? null;

        return $domain !== null && $domain['verified'] ? $domain['agent_id'] : null;
    }

    /**
     * The token a saved domain, verified or not, answers its check with. A
     * waiting domain must answer too: the check is what verifies it.
     */
    public function verificationTokenFor(string $host): ?string
    {
        return $this->domains()[$this->normalize($host)]['token'] ?? null;
    }

    /** Whether the domain is saved for an agent, verified or not. */
    public function isSaved(string $host): bool
    {
        return $this->verificationTokenFor($host) !== null;
    }

    public function forget(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
    }

    /**
     * Every saved domain of an agent that still exists, keyed by host.
     *
     * @return array<string, array{agent_id: int, token: string, verified: bool}>
     */
    private function domains(): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        return Cache::memo()->remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_TTL_MINUTES), fn (): array => AgentDomain::query()
            ->whereHas('agent')
            ->get(['host', 'agent_id', 'verification_token', 'verified_at'])
            ->mapWithKeys(fn (AgentDomain $domain): array => [$domain->host => [
                'agent_id' => $domain->agent_id,
                'token' => $domain->verification_token,
                'verified' => $domain->isVerified(),
            ]])
            ->all());
    }

    private function normalize(string $host): string
    {
        return Str::of($host)->lower()->rtrim('.')->toString();
    }
}
