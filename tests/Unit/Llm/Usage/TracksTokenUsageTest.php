<?php

use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlTrainJob;
use Domain\Knowledge\Source\Web\Middleware\ThrottleFirecrawl;
use Domain\Llm\Usage\TracksTokenUsage;
use Domain\Llm\Usage\TrackTokenUsage;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * A class method always wins over a trait method, so a job that uses
 * TracksTokenUsage and also defines its own middleware() silently loses the
 * token-usage middleware. These tests make sure every job that opts into
 * token tracking still returns the TrackTokenUsage middleware.
 */

/** @return list<class-string> */
function classesUsingTracksTokenUsage(): array
{
    $classes = [];

    $files = Finder::create()->files()->in(base_path('src'))->name('*.php')->contains('TracksTokenUsage');

    foreach ($files as $file) {
        $relative = Str::of($file->getRealPath())
            ->after(realpath(base_path('src')).DIRECTORY_SEPARATOR)
            ->beforeLast('.php')
            ->replace(DIRECTORY_SEPARATOR, '\\')
            ->toString();

        // src/App → App\, src/Domain → Domain\, src/Support → Support\.
        if (! class_exists($relative)) {
            continue;
        }

        if (in_array(TracksTokenUsage::class, class_uses_recursive($relative), true)) {
            $classes[] = $relative;
        }
    }

    return $classes;
}

it('finds the jobs that opt into token tracking', function (): void {
    expect(classesUsingTracksTokenUsage())->toContain(WebsiteUrlTrainJob::class);
});

it('keeps TrackTokenUsage in the middleware of every job using TracksTokenUsage', function (): void {
    foreach (classesUsingTracksTokenUsage() as $class) {
        $job = new ReflectionClass($class)->newInstanceWithoutConstructor();

        $middleware = collect($job->middleware())->map(fn (object $m): string => $m::class)->all();

        expect(in_array(TrackTokenUsage::class, $middleware, true))
            ->toBeTrue("{$class} lost the TrackTokenUsage middleware");
    }
});

it('runs the FireCrawl throttle before token tracking on website training', function (): void {
    $job = new ReflectionClass(WebsiteUrlTrainJob::class)->newInstanceWithoutConstructor();

    expect(array_map(fn (object $m): string => $m::class, $job->middleware()))
        ->toBe([ThrottleFirecrawl::class, TrackTokenUsage::class]);
});
