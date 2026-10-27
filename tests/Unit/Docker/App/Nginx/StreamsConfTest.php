<?php

declare(strict_types=1);

use App\Http\Controllers\Chat\Messages\MessageSpeechController;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

/*
 * docker/app/nginx/streams.conf sends the routes that keep a PHP worker busy
 * while the model writes to a PHP-FPM pool of their own. Its path pattern
 * must name every such route, and only those: a streaming route it misses
 * takes the dashboard's workers again, and a page it catches waits behind
 * busy chats.
 */

/** The location pattern of streams.conf, as a PHP regex (nginx uses PCRE too). */
function scfPattern(): string
{
    $pattern = Str::match('/^location ~ (\S+) \{$/m', File::get(base_path('docker/app/nginx/streams.conf')));

    expect($pattern)->not->toBe('');

    return '#'.$pattern.'#';
}

/**
 * The controllers that answer with a chat turn as it streams (they use
 * SseResponder or JsonTurnResponder), and the read-aloud audio, which waits
 * for the speech service.
 *
 * @return list<class-string>
 */
function scfStreamingControllers(): array
{
    $directory = base_path('src/App/Http/Controllers');

    return collect(File::allFiles($directory))
        ->filter(fn (SplFileInfo $file): bool => Str::contains($file->getContents(), [
            'use App\Http\Responses\SseResponder;',
            'use App\Http\Responses\JsonTurnResponder;',
        ]))
        ->map(fn (SplFileInfo $file): string => Str::of($file->getRelativePathname())
            ->chopEnd('.php')
            ->replace('/', '\\')
            ->prepend('App\\Http\\Controllers\\')
            ->toString())
        ->push(MessageSpeechController::class)
        ->values()
        ->all();
}

/** The route's path with every parameter filled in, the way a browser sends it. */
function scfSamplePath(Route $route): string
{
    return '/'.Str::replaceMatches('/\{[^}]+\}/', 'x', Str::chopStart($route->uri(), '/'));
}

it('finds the streaming controllers', function (): void {
    expect(scfStreamingControllers())->toHaveCount(6);
});

it('sends every streaming route to the stream pool', function (): void {
    $controllers = scfStreamingControllers();

    $missed = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => in_array($route->getControllerClass(), $controllers, true))
        ->map(scfSamplePath(...))
        ->reject(fn (string $path): bool => preg_match(scfPattern(), $path) === 1)
        ->values()
        ->all();

    expect($missed)->toBe([]);
});

it('sends no other route to the stream pool', function (): void {
    $controllers = scfStreamingControllers();

    $caught = collect(Router::getRoutes()->getRoutes())
        ->reject(fn (Route $route): bool => in_array($route->getControllerClass(), $controllers, true))
        ->map(scfSamplePath(...))
        ->filter(fn (string $path): bool => preg_match(scfPattern(), $path) === 1)
        ->values()
        ->all();

    expect($caught)->toBe([]);
});

it('gives the site install.sh writes the same paths', function (): void {
    $installer = Str::match('/^    location ~ (\^\/\(\?:chat\S+) \{$/m', File::get(base_path('install.sh')));

    expect('#'.$installer.'#')->toBe(scfPattern());
});

it('allows a trailing slash, as the router does', function (): void {
    expect(preg_match(scfPattern(), '/widget/stream/'))->toBe(1)
        ->and(preg_match(scfPattern(), '/chat/a/stream/b/'))->toBe(1)
        ->and(preg_match(scfPattern(), '/chat/a/conversations/b/stop'))->toBe(0);
});
