<?php

declare(strict_types=1);

namespace Tests\Support\Agent;

use Domain\Agent\Contracts\DnsTxtResolver;

/**
 * DNS TXT records from a fixed map instead of real DNS, so custom-domain
 * tests never leave the machine. Every host asked for is kept, so a test can
 * check which record was looked up.
 */
final class FakeDnsTxtResolver implements DnsTxtResolver
{
    /** @var list<string> */
    public array $asked = [];

    /**
     * @param  array<string, list<string>>  $records  host => TXT values
     */
    public function __construct(
        private readonly array $records = [],
    ) {}

    /**
     * Bind a fake with these records in the container and return it.
     *
     * @param  array<string, list<string>>  $records  host => TXT values
     */
    public static function bind(array $records = []): self
    {
        $fake = new self($records);
        app()->instance(DnsTxtResolver::class, $fake);

        return $fake;
    }

    public function txtRecords(string $host): array
    {
        $this->asked[] = $host;

        return $this->records[$host] ?? [];
    }
}
