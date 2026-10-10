<?php

namespace Tests\Support;

use Illuminate\Support\ServiceProvider;

/**
 * Registers service providers again on the booted test app and runs the
 * booting callbacks they add, in order: what a fresh boot does after the
 * providers register and before they boot. For tests of the decisions made at
 * that point, such as applying the admin settings and leaving out Cashier's
 * routes while billing is off.
 */
final class ReregisterProviders
{
    /**
     * @param  class-string<ServiceProvider>  ...$providers
     */
    public static function andRunBootingCallbacks(string ...$providers): void
    {
        $app = app();
        $callbacks = fn (): array => (fn (): array => $this->bootingCallbacks)->call($app);
        $before = count($callbacks());

        foreach ($providers as $provider) {
            new $provider($app)->register();
        }

        foreach (array_slice($callbacks(), $before) as $callback) {
            $callback($app);
        }
    }
}
