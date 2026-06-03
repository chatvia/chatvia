<?php

namespace Domain\Integration\Connector\Services;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Illuminate\Support\Collection;

/**
 * Thin accessor over `config/connectors.php`. The config is the source of
 * truth — this service just hands out typed records the rest of the app
 * (controllers, Vue serializers, the authorizer) can use without hand-
 * touching arrays.
 */
class ConnectorCatalog
{
    /**
     * @return Collection<string, ConnectorData>
     */
    public function all(): Collection
    {
        return collect(config('connectors', []))
            ->filter(fn (array $entry) => $entry['visible'] ?? true)
            ->map(fn (array $entry): ConnectorData => ConnectorData::fromArray($entry))
            ->sortBy(fn (ConnectorData $c): int => $c->priority)
            ->keyBy(fn (ConnectorData $c): string => $c->slug);
    }

    public function find(string $slug): ?ConnectorData
    {
        $entry = config('connectors.'.$slug);
        if (! is_array($entry)) {
            return null;
        }

        return ConnectorData::fromArray($entry);
    }

    /**
     * Token + no-auth connectors are connectable via the synchronous
     * "Connect" endpoint. OAuth has its own redirect dance.
     */
    public function findTokenConnector(string $slug): ?ConnectorData
    {
        $connector = $this->find($slug);

        if (! $connector || $connector->comingSoon) {
            return null;
        }

        if (! in_array($connector->authStrategy, ['token', 'none'], true)) {
            return null;
        }

        return $connector;
    }
}
