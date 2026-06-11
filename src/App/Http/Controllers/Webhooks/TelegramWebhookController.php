<?php

namespace App\Http\Controllers\Webhooks;

use Domain\Channel\Telegram\Jobs\HandleTelegramUpdateJob;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Receives Telegram bot updates. The {secret} path segment is the per-bot
 * `webhook_secret` (routes to the agent); Telegram also echoes it in the
 * X-Telegram-Bot-Api-Secret-Token header, which we verify. Each update is
 * processed once (idempotency on `update_id`) and handed to a queued job so the
 * webhook returns immediately. Always replies 200 — a non-2xx makes Telegram
 * retry, and an unknown/spoofed secret should just be a silent no-op.
 */
class TelegramWebhookController
{
    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $ok = response()->json(['ok' => true]);

        $bot = AgentTelegramBot::query()
            ->with('agent')
            ->where('webhook_secret', $secret)
            ->where('enabled', true)
            ->first();

        if ($bot === null || ! hash_equals($bot->webhook_secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            return $ok;
        }

        // Process each update at most once — Telegram keeps re-delivering an
        // unacknowledged update for up to 24 hours, so the 1-day TTL covers it.
        $updateId = $request->input('update_id');
        if ($updateId !== null && ! Cache::add("tg:update:{$bot->id}:{$updateId}", true, now()->addDay())) {
            return $ok;
        }

        $text = $request->input('message.text');
        $chatId = $request->input('message.chat.id');

        if (is_string($text) && trim($text) !== '' && $chatId !== null) {
            HandleTelegramUpdateJob::dispatch(
                $bot,
                (string) $chatId,
                $text,
                [
                    'first_name' => $request->input('message.from.first_name'),
                    'username' => $request->input('message.from.username'),
                ],
            );
        }

        return $ok;
    }
}
