<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\QueryException;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Manager;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Throwable;

/**
 * Puts the values saved in the admin panel into config, so the code that
 * reads `config('services.google.client_id')`, `config('chatvia.ai.reranker')`
 * and the like (our own code, Socialite, the mailer) gets them with no change.
 *
 * Each settings property names the config key it fills ({@see ConfigKey}). A
 * blank property leaves the key at its value from config/*.php, which a
 * deployment may still preset in .env. That value is remembered before the
 * first apply, so clearing a field in a long-running worker restores it.
 *
 * {@see apply()} runs when the app boots, again before every queued job, and
 * after every save, so a change applies right away everywhere. Settings come
 * from the settings cache: one cache read per class when nothing changed.
 */
final class SettingsConfig
{
    /** @var array<string, mixed>|null The config value of each mapped key before any setting was applied. */
    private ?array $defaults = null;

    private ?string $appliedHash = null;

    public function __construct(
        private readonly Config $config,
        private readonly Container $container,
    ) {}

    /**
     * @return list<class-string<AdminSettings>>
     */
    public function classes(): array
    {
        return array_values($this->config->get('settings.settings', []));
    }

    public function apply(): void
    {
        $this->defaults ??= $this->snapshot();

        $values = [];

        foreach ($this->classes() as $class) {
            $saved = $this->saved($class);

            foreach ($class::configKeys() as $property => $keys) {
                foreach ($keys as $key) {
                    $values[$key->key] = filled($saved[$property] ?? null) ? $saved[$property] : $this->default($key);
                }
            }
        }

        $hash = md5(serialize($values));

        if ($hash === $this->appliedHash) {
            return;
        }

        foreach ($values as $key => $value) {
            $this->config->set($key, $value);
        }

        // The translator took its locale from config when it was built.
        if ($this->container instanceof Application && is_string($values['app.locale'] ?? null)) {
            $this->container->setLocale($values['app.locale']);
        }

        // A disk's config is a whole array that depends on the storage chosen.
        $this->container->make(StorageDisks::class)->apply();

        if ($this->appliedHash !== null) {
            $this->forgetResolvedClients();
        }

        $this->appliedHash = $hash;
    }

    /**
     * The value a key has while nothing is saved: this app's URL for a
     * callback, otherwise the value from config/*.php (or .env).
     */
    public function default(ConfigKey $key): mixed
    {
        if ($key->defaultPath !== null) {
            return url($key->defaultPath);
        }

        $this->defaults ??= $this->snapshot();

        return $this->defaults[$key->key] ?? null;
    }

    /**
     * The default of a settings property: the default of its first config key.
     *
     * @param  class-string<AdminSettings>  $class
     */
    public function defaultOf(string $class, string $property): mixed
    {
        $key = $class::configKeys()[$property][0] ?? null;

        return $key === null ? null : $this->default($key);
    }

    /**
     * The saved values of one settings class. Empty before `migrate` (no
     * table) or without a database (a build).
     *
     * @param  class-string<AdminSettings>  $class
     * @return array<string, mixed>
     */
    private function saved(string $class): array
    {
        try {
            return $this->container->make($class)->toArray();
        } catch (QueryException) {
            return [];
        } catch (Throwable $e) {
            // The cache or database is unreachable: every key keeps its
            // config value until the next apply.
            report($e);

            return [];
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $defaults = [];

        foreach ($this->classes() as $class) {
            foreach ($class::configKeys() as $keys) {
                foreach ($keys as $key) {
                    $defaults[$key->key] = $this->config->get($key->key);
                }
            }
        }

        return $defaults;
    }

    /**
     * The mailer and the Socialite drivers keep the config they were built
     * with. A queue worker builds them once, so drop them after a change.
     */
    private function forgetResolvedClients(): void
    {
        if ($this->container->resolved('mail.manager')) {
            $mail = $this->container->make('mail.manager');

            if ($mail instanceof MailManager) {
                $mail->forgetMailers();
            }
        }

        if ($this->container->resolved(Socialite::class)) {
            $socialite = $this->container->make(Socialite::class);

            if ($socialite instanceof Manager) {
                $socialite->forgetDrivers();
            }
        }
    }
}
