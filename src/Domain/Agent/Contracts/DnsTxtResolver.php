<?php

declare(strict_types=1);

namespace Domain\Agent\Contracts;

use Domain\Agent\Services\CustomDomain\SystemDnsTxtResolver;
use Illuminate\Container\Attributes\Bind;

/**
 * Reads the DNS TXT records of a host name, so a custom domain can prove it
 * belongs to the workspace that adds it. An interface so tests swap in a fake
 * and never query real DNS.
 */
#[Bind(SystemDnsTxtResolver::class)]
interface DnsTxtResolver
{
    /**
     * The text of every TXT record on the host; empty when there are none or
     * the lookup fails.
     *
     * @return list<string>
     */
    public function txtRecords(string $host): array;
}
