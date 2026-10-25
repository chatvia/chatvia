<?php

namespace App\Http\Controllers\Webhooks;

use Domain\Channel\Telegram\Actions\ReceiveTelegramUpdateAction;
use Domain\Channel\Telegram\Data\TelegramUpdateData;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Telegram bot updates. The {secret} path segment is the per-bot
 * `webhook_secret` (routes to the agent); the X-Telegram-Bot-Api-Secret-Token
 * header must carry the bot's separate `header_secret`, so the URL alone
 * cannot forge an update. Verified updates go
 * to {@see ReceiveTelegramUpdateAction}, which de-duplicates and queues them so
 * the webhook returns immediately. Always replies 200 — a non-2xx makes
 * Telegram retry, and an unknown/spoofed secret should just be a silent no-op.
 */
class TelegramWebhookController
{
    public function __invoke(Request $request, ReceiveTelegramUpdateAction $receive, string $secret): JsonResponse
    {
        $bot = AgentTelegramBot::query()
            ->with('agent')
            ->where('webhook_secret', $secret)
            ->where('is_enabled', true)
            ->first();

        if ($bot !== null && hash_equals($bot->header_secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            $receive->execute($bot, TelegramUpdateData::fromPayload($request->all()));
        }

        return response()->json(['ok' => true]);
    }
}
