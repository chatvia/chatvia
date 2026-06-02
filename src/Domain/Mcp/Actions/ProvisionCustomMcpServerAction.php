<?php

namespace Domain\Mcp\Actions;

use App\Workspace\Models\User;
use Domain\Mcp\Data\ProvisionCustomMcpServerData;
use Domain\Mcp\Data\ProvisionedMcpServerData;
use Domain\Mcp\Models\AgentMcpServer;
use Domain\Mcp\Services\McpCatalog;
use Illuminate\Support\Facades\DB;

class ProvisionCustomMcpServerAction
{
    public function __construct(
        private readonly McpCatalog $catalog,
    ) {}

    public function execute(ProvisionCustomMcpServerData $data): ProvisionedMcpServerData
    {
        return DB::transaction(function () use ($data): ProvisionedMcpServerData {
            $server = AgentMcpServer::create([
                'user_id' => $data->user->id,
                'connector_slug' => null,
                'name' => $data->name,
                'slug' => $this->uniqueCustomSlug($data->user, $data->name),
                'url' => $data->url,
                'transport' => 'streamable_http',
                'auth_type' => $data->strategy === 'token' ? 'bearer' : 'none',
                'auth_value' => $data->strategy === 'token' ? ($data->token ?? '') : null,
                'headers' => null,
                'timeout_ms' => 30000,
                'is_enabled' => true,
            ]);

            $tools = $this->catalog->refresh($server);

            return new ProvisionedMcpServerData(
                server: $server->fresh(),
                tools_count: count($tools),
            );
        });
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
