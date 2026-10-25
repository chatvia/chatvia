<?php

declare(strict_types=1);

namespace Domain\Agent\Services\CustomDomain;

use Domain\Agent\Contracts\DnsTxtResolver;

/**
 * Reads TXT records through the server's own DNS resolver. A record split
 * into several strings is joined back into one value.
 */
final class SystemDnsTxtResolver implements DnsTxtResolver
{
    public function txtRecords(string $host): array
    {
        $records = rescue(fn (): array|false => dns_get_record($host, DNS_TXT), false, report: false);

        if (! is_array($records)) {
            return [];
        }

        return collect($records)
            ->map(fn (array $record): string => isset($record['entries']) ? implode('', (array) $record['entries']) : (string) ($record['txt'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }
}
