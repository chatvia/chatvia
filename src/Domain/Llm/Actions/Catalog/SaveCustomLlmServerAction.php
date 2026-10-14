<?php

declare(strict_types=1);

namespace Domain\Llm\Actions\Catalog;

use Domain\Llm\Data\Catalog\CustomLlmServerData;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\LlmProvider;

/**
 * Adds a server of your own (Settings → AI providers), or changes one: its
 * name, address, extra headers, and its key when a new one is given. A
 * server of your own is the only provider that keeps its address and key on
 * its row; it is turned on again when it was off.
 */
final class SaveCustomLlmServerAction
{
    public function execute(CustomLlmServerData $data, ?LlmProvider $server = null): LlmProvider
    {
        $server ??= new LlmProvider(['kind' => LlmProviderEnum::CUSTOM]);

        $server->fill([
            'name' => trim($data->name),
            'base_url' => rtrim(trim($data->baseUrl), '/'),
            'headers' => $data->headers !== [] ? $data->headers : null,
            'is_enabled' => true,
        ]);

        if (filled($data->apiKey)) {
            $server->api_key = $data->apiKey;
        }

        $server->save();

        return $server;
    }
}
