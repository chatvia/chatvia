<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\QueryException;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Spatie\LaravelPdf\Drivers\PdfDriver;
use Support\License\LicenseFeature;
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
    /** The options on Settings → Splitting, which need a license key. */
    public const array SPLITTING_KEYS = [
        'chatvia.ai.chunking.unit',
        'chatvia.ai.chunking.size',
        'chatvia.ai.chunking.overlap',
        'chatvia.ai.chunking.minimum',
        'chatvia.ai.chunking.strategies.markdown',
        'chatvia.ai.chunking.strategies.text',
        'chatvia.ai.chunking.strategies.transcript',
        'chatvia.ai.tokenizer',
    ];

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

        // Hiding the credit needs a license key for this install's domain,
        // however the switch was turned off (panel, chatvia:settings:set or config).
        $credit = 'chatvia.powered_by';

        $license = $this->container->make(CreditLicense::class);

        if (($values[$credit] ?? $this->config->get($credit)) !== true
            && ! $license->allowsHidingCredit($values['chatvia.license.key'] ?? null)) {
            $values[$credit] = true;
        }

        // The Enterprise features follow the key alone; nothing else can switch them on.
        $values['chatvia.license.enterprise'] = $license->allows($values['chatvia.license.key'] ?? null, LicenseFeature::Enterprise);

        // Any valid key lifts the free-use limits. Without one, texts are split
        // with the defaults; saved Splitting options stay saved but unused.
        $values['chatvia.license.licensed'] = $license->allowsHidingCredit($values['chatvia.license.key'] ?? null);

        if (! $values['chatvia.license.licensed']) {
            foreach (self::SPLITTING_KEYS as $key) {
                $values[$key] = $this->defaults[$key] ?? null;
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
        if ($this->container instanceof Application && filled($values['app.locale'] ?? null)) {
            $this->container->setLocale($values['app.locale']);
        }

        // A disk's config is a whole array that depends on the storage chosen.
        $this->container->make(StorageDisks::class)->apply();

        // Pulse and Spectra read their own switch only while they boot.
        $this->container->make(ToolRecording::class)->apply();

        if ($this->appliedHash !== null) {
            $this->forgetResolvedClients();
        }

        $this->appliedHash = $hash;
    }

    /**
     * The value a key has while nothing is saved: this app's URL for a
     * callback, otherwise the value from config/*.php (or .env).
     *
     * A callback is built from `app.url`, not `url()`: this runs while the
     * app boots, before https is forced and before the proxy headers are
     * trusted, so `url()` would give http:// behind a proxy that ends TLS.
     */
    public function default(ConfigKey $key): mixed
    {
        if ($key->defaultPath !== null) {
            return Str::rtrim((string) $this->config->get('app.url'), '/').$key->defaultPath;
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
     * The mailer, the Socialite drivers and the PDF drivers (the WeasyPrint
     * path, the Cloudflare and Gotenberg keys) keep the config they were
     * built with. A queue worker builds them once, so drop them after a
     * change.
     */
    private function forgetResolvedClients(): void
    {
        if ($this->container instanceof IlluminateContainer) {
            $this->container->forgetInstance('laravel-pdf.driver.weasyprint');
            $this->container->forgetInstance('laravel-pdf.driver.cloudflare');
            $this->container->forgetInstance('laravel-pdf.driver.gotenberg');
            $this->container->forgetInstance(PdfDriver::class);
        }

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
