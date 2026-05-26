<?php

namespace Domain\Chat\Jobs;

use Domain\Agents\Models\AgentFunction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Populates `functions.embedding` for a single function. Dispatched by
 * the AgentFunction observer on create/update so user-defined webhook
 * functions are immediately scorable by the semantic router after they
 * appear or change.
 */
class EmbedFunctionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $functionId) {}

    public function handle(): void
    {
        $function = AgentFunction::query()->find($this->functionId);
        if (! $function) {
            return;
        }

        try {
            $text = $function->name."\n\n".$function->description;
            $function->forceFill([
                'embedding' => embed($text),
                'embedded_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::warning('Function embedding failed', [
                'function_id' => $function->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
