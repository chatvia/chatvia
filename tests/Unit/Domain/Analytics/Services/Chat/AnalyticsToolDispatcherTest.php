<?php

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Services\Chat\AnalyticsToolDispatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The owner's "Ask your data" chat hands tool results to its model. When a
 * call throws, the model must get a fixed sentence: the exception message can
 * carry internal URLs or response bodies. The log keeps the exception type,
 * without the workspace.
 */
it('gives the model a generic message and logs the failure without the workspace', function (): void {
    $workspace = User::factory()->create()->workspace();
    Http::fake(fn (): never => throw new RuntimeException('connect to http://10.0.0.7:8080/internal?token=secret failed'));
    Log::spy();

    $result = app(AnalyticsToolDispatcher::class)->dispatch(
        $workspace,
        'get-account-overview-tool',
        Str::rtrim((string) config('app.url'), '/').'/mcp/account-analytics',
        [],
    );

    expect($result->isError)->toBeTrue()
        ->and($result->text)->toBe('The tool failed unexpectedly. Try again later.')
        ->and($result->text)->not->toContain('10.0.0.7');

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($workspace): bool {
        $logged = json_encode($context);

        return $message === 'Analytics tool call failed'
            && $context['tool'] === 'get-account-overview-tool'
            && ! $context['exception'] instanceof Throwable && filled($context['exception'])
            && ! Str::contains((string) $logged, $workspace->external_id)
            && ! Str::contains((string) $logged, 'token=secret');
    })->once();
});
