<?php

namespace App\Admin\Settings;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Puts the credentials saved in the admin panel into config, over the values
 * from the .env file, so the code that reads `config('services.google.client_id')`
 * and the like gets them with no change. A blank or missing saved value leaves
 * the .env value in place.
 *
 * The rows are cached as ciphertext (any write clears the cache) and decrypted
 * in each process. Long-running queue workers call {@see apply()} before every
 * job; that is one cache read when nothing changed.
 */
final class SettingsStore
{
    private const string CACHE_KEY = 'admin_settings';

    /**
     * Config values from before the first override: the .env values.
     *
     * @var array<string, mixed>|null
     */
    private ?array $defaults = null;

    private ?string $appliedHash = null;

    public function __construct(
        private readonly Config $config,
        private readonly Cache $cache,
        private readonly ConnectionResolverInterface $db,
    ) {}

    public function apply(): void
    {
        $this->captureDefaults();

        $rows = $this->rows();
        $hash = md5(serialize($rows));

        if ($hash === $this->appliedHash) {
            return;
        }

        $saved = $this->decrypt($rows);

        foreach (SettingGroup::cases() as $group) {
            foreach ($group->fields() as $field) {
                $value = $saved[$group->value][$field->name] ?? null;

                // Setting the default back matters in a worker: a value removed
                // in the panel must fall back to .env, not keep the old one.
                $this->config->set($field->config, filled($value) ? $value : $this->defaults[$field->config]);
            }
        }

        $this->appliedHash = $hash;
    }

    /**
     * The value of a field without the admin panel (from the .env file).
     */
    public function envValue(SettingField $field): mixed
    {
        $this->captureDefaults();

        return $this->defaults[$field->config] ?? null;
    }

    public function status(SettingGroup $group, ?AdminSetting $setting): SettingStatus
    {
        if ($this->missingFields($group, $setting) === []) {
            return $setting?->hasSavedValues() ? SettingStatus::Configured : SettingStatus::FromEnv;
        }

        $anySet = array_filter($group->fields(), fn (SettingField $field): bool => $this->isSet($field, $setting)) !== [];

        return $anySet ? SettingStatus::Incomplete : SettingStatus::Missing;
    }

    /**
     * The required fields with neither a saved nor an .env value.
     *
     * @return list<SettingField>
     */
    public function missingFields(SettingGroup $group, ?AdminSetting $setting): array
    {
        return array_values(array_filter(
            $group->requiredFields(),
            fn (SettingField $field): bool => ! $this->isSet($field, $setting),
        ));
    }

    private function isSet(SettingField $field, ?AdminSetting $setting): bool
    {
        return filled($setting?->value($field)) || filled($this->envValue($field));
    }

    public function forget(): bool
    {
        return $this->cache->forget(self::CACHE_KEY);
    }

    private function captureDefaults(): void
    {
        if ($this->defaults !== null) {
            return;
        }

        $this->defaults = [];

        foreach (SettingGroup::cases() as $group) {
            foreach ($group->fields() as $field) {
                $this->defaults[$field->config] = $this->config->get($field->config);
            }
        }
    }

    /**
     * The saved payloads, still encrypted, keyed by group.
     *
     * @return array<string, string>
     */
    private function rows(): array
    {
        try {
            return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => $this->db->connection()
                ->table('admin_settings')
                ->whereNotNull('payload')
                ->pluck('payload', 'key')
                ->all());
        } catch (QueryException) {
            // No table yet (a fresh install before `migrate`) or no database
            // (composer scripts in a build): the .env values apply.
            return [];
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @param  array<string, string>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function decrypt(array $rows): array
    {
        $saved = [];

        foreach ($rows as $key => $ciphertext) {
            try {
                $saved[$key] = (new AdminSetting)->setRawAttributes(['payload' => $ciphertext])->payload ?? [];
            } catch (DecryptException $e) {
                // Encrypted with another APP_KEY (add the old key to
                // APP_PREVIOUS_KEYS). Run on the .env values rather than fail
                // every request.
                report($e);
            }
        }

        return $saved;
    }
}
