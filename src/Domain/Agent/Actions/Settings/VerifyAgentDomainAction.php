<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Settings;

use Domain\Agent\Contracts\DnsTxtResolver;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\CustomDomain\CustomDomainVerification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;

/**
 * Turns an agent's pending custom domain on once its owner proved they
 * control it: the `_chatvia.<domain>` TXT record holds the agent's token. A
 * domain another agent already uses is refused with the same words as any
 * unusable domain, so the answer never tells that another workspace has it.
 */
class VerifyAgentDomainAction
{
    public function __construct(
        private readonly DnsTxtResolver $dns,
    ) {}

    /**
     * @throws DomainValidationException when there is nothing to verify, the record is missing or the domain is in use
     */
    public function execute(Agent $agent): void
    {
        $domain = (string) $agent->pending_domain;
        $token = (string) $agent->domain_verification_token;

        if ($domain === '' || $token === '') {
            throw new DomainValidationException('domain', __('Add a custom domain first.'));
        }

        $found = collect($this->dns->txtRecords(CustomDomainVerification::recordName($domain)))
            ->contains(fn (string $record): bool => Str::trim($record, " \t\n\r\0\x0B\"") === $token);

        if (! $found) {
            throw new DomainValidationException('domain', __("We couldn't find the TXT record for this domain yet. DNS changes can take a while, so try again later."));
        }

        if (Agent::withTrashed()->where('domain', $domain)->whereKeyNot($agent->id)->exists()) {
            throw $this->unavailable();
        }

        try {
            $agent->update(['domain' => $domain, 'pending_domain' => null]);
        } catch (UniqueConstraintViolationException) {
            throw $this->unavailable();
        }
    }

    private function unavailable(): DomainValidationException
    {
        return new DomainValidationException('domain', __("This domain can't be used. Choose another domain."));
    }
}
