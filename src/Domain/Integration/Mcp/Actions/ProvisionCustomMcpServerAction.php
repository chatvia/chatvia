<?php

namespace Domain\Integration\Mcp\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Data\ProvisionCustomMcpServerData;
use Domain\Integration\Mcp\Data\ProvisionedMcpServerData;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpCatalog;
use Throwable;

class ProvisionCustomMcpServerAction
{
    public function __construct(
        private readonly McpCatalog $catalog,
    ) {}

    public function execute(ProvisionCustomMcpServerData $data): ProvisionedMcpServerData
    {
        // refresh() is remote HTTP — never hold a transaction across it; compensate on failure.
        $server = AgentMcpServer::create([
            'user_id' => $data->user->id,
            'category' => McpServerCategory::Custom,
            'connector_slug' => null,
            'name' => $data->name,
            'slug' => $this->uniqueCustomSlug($data->user, $data->name),
            'url' => $data->url,
            'transport' => 'streamable_http',
            'auth_type' => $data->strategy === 'token' ? 'bearer' : 'none',
            'auth_value' => $data->strategy === 'token' ? ($data->token ?? '') : null,
            'headers' => null,
            'timeout_ms' => 300000,
            'is_enabled' => true,
        ]);

        try {
            $tools = $this->catalog->refresh($server);
        } catch (Throwable $e) {
            $server->delete();

            throw $e;
        }

        return new ProvisionedMcpServerData(
            server: $server->fresh(),
            tools_count: count($tools),
        );
    }

    private function uniqueCustomSlug(User $user, string $name): string
    {
        $base = AgentMcpServer::makeSlug($name);
        $slug = $base;
        $i = 2;

        while ($user->mcpServers()->where('slug', $slug)->exists()) {
            $slug = substr($base, 0, 38).'_'.$i;
            $i++;
        }

        return $slug;
    }
}
