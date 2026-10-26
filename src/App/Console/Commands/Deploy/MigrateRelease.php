<?php

declare(strict_types=1);

namespace App\Console\Commands\Deploy;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * The database steps of every start and deploy, in order, so the Docker image
 * (`chatvia-migrate`) and Deployer (`deploy.php`) run the same list: the tool
 * databases, the migrations, the embedding checks, then the built-in tools.
 * Every step is safe to repeat. A failed check stops here; the built-in
 * tools only warn, because they need an embedding API key that a new install
 * has not got yet (the admin setup adds them when it finishes).
 */
#[Description('Prepare the database for the release: tool databases, migrations, embedding checks and the built-in tools.')]
#[Signature('chatvia:deploy:migrate')]
class MigrateRelease extends Command
{
    public function handle(): int
    {
        $steps = [
            // The analytics, Pulse and Spectra tables live in databases of
            // their own, which have to be there before the migrations run.
            'chatvia:db:create-tool-databases' => [],
            'migrate' => ['--force' => true],
            'chatvia:search:check-embedding-storage' => [],
            'chatvia:search:vector-indexes' => [],
        ];

        foreach ($steps as $command => $arguments) {
            if ($this->call($command, $arguments) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        rescue(
            fn (): int => $this->call('chatvia:mcp:sync-builtin'),
            function (Throwable $exception): void {
                $this->components->warn("The built-in tools were not added to the tool catalog: {$exception->getMessage()}");
                $this->line('  They need the embedding provider\'s API key (admin panel, Settings → AI providers).');
                $this->line('  The admin setup adds them when it finishes; on an install that is set up, run');
                $this->line('  php artisan chatvia:mcp:sync-builtin');
            },
            report: false,
        );

        return self::SUCCESS;
    }
}
