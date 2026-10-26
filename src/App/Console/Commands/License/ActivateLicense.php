<?php

declare(strict_types=1);

namespace App\Console\Commands\License;

use App\Admin\Settings\Actions\ActivateLicenseAction;
use App\Admin\Settings\CreditLicense;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Activates a license key for this install's domain at chatvia.ai and saves
 * it ({@see ActivateLicenseAction}), like saving it in the admin panel under
 * Settings → License. The key is asked for when it is not passed; `--stdin`
 * reads it from standard input, so the installer can pass it without it
 * showing in the process list.
 */
#[Description('Activate a license key for this install at chatvia.ai and save it.')]
#[Signature('chatvia:license:activate {key? : The license key (asked for when left out)} {--stdin : Read the key from standard input}')]
class ActivateLicense extends Command
{
    public function handle(ActivateLicenseAction $activateLicense, CreditLicense $license): int
    {
        $key = $this->key();

        if (blank($key)) {
            $this->error('Give a license key.');

            return self::FAILURE;
        }

        $activation = $activateLicense->execute($key);

        if ($activation->key === null) {
            $this->error((string) $activation->message);

            return self::FAILURE;
        }

        $this->info("The license is active for {$license->host()}, licensed to {$license->read($activation->key)?->licensee}.");

        return self::SUCCESS;
    }

    private function key(): string
    {
        if ($this->option('stdin')) {
            $line = fgets(STDIN);

            return $line === false ? '' : Str::trim($line);
        }

        return Str::trim((string) ($this->argument('key') ?? $this->secret('License key')));
    }
}
