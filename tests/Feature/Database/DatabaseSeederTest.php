<?php

use App\Identity\Workspace\Models\User;
use Database\Seeders\AnalyticsDemoSeeder;
use Database\Seeders\ChatSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TopicsSeeder;
use Domain\Agent\Models\Agent;
use Domain\Llm\Models\Llm;

/**
 * The demo part of the seeder (a login with a well-known password, fake chats
 * and analytics for every agent) must never run against production, while the
 * reference data it also seeds is needed everywhere.
 */
it('seeds only reference data in production and refuses the demo account', function (): void {
    $this->app['env'] = 'production';
    config(['chatvia.seed.admin_email' => 'never-created@example.com']);

    $users = User::count();
    $agents = Agent::count();

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])
        ->expectsOutputToContain('demo data is never seeded in production')
        ->assertSuccessful();

    expect(User::where('email', 'never-created@example.com')->exists())->toBeFalse()
        ->and(User::count())->toBe($users)
        ->and(Agent::count())->toBe($agents)
        ->and(Llm::count())->toBeGreaterThan(0);
});

it('refuses each demo seeder on its own in production', function (string $seeder): void {
    $this->app['env'] = 'production';

    $this->artisan('db:seed', ['--class' => $seeder, '--force' => true])
        ->expectsOutputToContain('demo data is never seeded in production')
        ->assertSuccessful();
})->with([
    'analytics' => AnalyticsDemoSeeder::class,
    'chats' => ChatSeeder::class,
    'topics' => TopicsSeeder::class,
]);
