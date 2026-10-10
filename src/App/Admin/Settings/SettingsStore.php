<?php

namespace App\Admin\Settings;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Puts the values saved in the admin panel into config, so the code that
 * reads `config('services.google.client_id')`, `config('billing.enabled')` and
 * the like gets them with no change. The admin panel is the only source for
 * these keys: a field with nothing saved gets its default (redirect URLs, the
 * billing defaults) or null. Typed values (toggles, numbers, dates) reach
 * config as their PHP type.
 *
 * The rows are cached as ciphertext (any write clears the cache) and decrypted
 * in each process. {@see apply()} runs before the providers boot, so every
 * provider already sees the saved values. Long-running queue workers call it
 * again before every job; that is one cache read when nothing changed.
 */
final class SettingsStore
{
    private const string CACHE_KEY = 'admin_settings';

    private ?string $appliedHash = null;

    public function __construct(
        private readonly Config $config,
        private readonly Cache $cache,
        private readonly ConnectionResolverInterface $db,
    ) {}

    public function apply(): void
    {
        $rows = $this->rows();
        $hash = md5(serialize($rows));

        if ($hash === $this->appliedHash) {
            return;
        }

        $saved = $this->decrypt($rows);

        foreach (SettingGroup::cases() as $group) {
            $values = $this->values($group, $saved[$group->value] ?? []);

            foreach ($group->fields() as $field) {
                // Every field is set, also the empty ones: in a worker, a value
                // removed in the panel must not keep its old value.
                $this->config->set($field->config, $values[$field->name]);

                foreach ($field->alsoFills as $config => $derive) {
                    $this->config->set($config, $derive($values[$field->name]));
                }
            }
        }

        $this->appliedHash = $hash;
    }

    /**
     * The value of each field of a group: the saved value as its type, or the
     * default. Fields are resolved in order, so a default can depend on an
     * earlier field (sign-up is open by default only while billing is off).
     *
     * @param  array<string, mixed>  $payload  The group's saved values.
     * @return array<string, bool|int|string|null> By field name.
     */
    public function values(SettingGroup $group, array $payload): array
    {
        $values = [];

        foreach ($group->fields() as $field) {
            $values[$field->name] = $field->cast($payload[$field->name] ?? null) ?? $field->defaultValue($values);
        }

        return $values;
    }

    public function status(SettingGroup $group, ?AdminSetting $setting): SettingStatus
    {
        // A group without required fields (billing) works on its defaults,
        // but it is only "configured" once something is saved.
        if (! $setting?->hasSavedValues()) {
            return SettingStatus::Missing;
        }

        return $this->missingFields($group, $setting) === [] ? SettingStatus::Configured : SettingStatus::Incomplete;
    }

    /**
     * The required fields that have no saved value.
     *
     * @return list<SettingField>
     */
    public function missingFields(SettingGroup $group, ?AdminSetting $setting): array
    {
        return array_values(array_filter(
            $group->requiredFields(),
            fn (SettingField $field): bool => blank($setting?->value($field)),
        ));
    }

    public function forget(): bool
    {
        return $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * The saved payloads, still encrypted, keyed by group.
     *
     * @return array<string, string>
     */
    private function rows(): array
    {
        try {
            return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => $this->query());
        } catch (QueryException) {
            // No table yet (a fresh install before `migrate`) or no database
            // (composer scripts in a build): nothing is configured.
            return [];
        } catch (Throwable $e) {
            report($e);

            // The cache is unreachable. Read the table directly, so a cache
            // outage does not switch billing and every credential off.
            try {
                return $this->query();
            } catch (Throwable) {
                return [];
            }
        }
    }

    /** @return array<string, string> */
    private function query(): array
    {
        return $this->db->connection()
            ->table('admin_settings')
            ->whereNotNull('payload')
            ->pluck('payload', 'key')
            ->all();
    }

    /**
     * Decrypts like the `encrypted:array` cast of {@see AdminSetting}, without
     * creating the model: this runs before the providers boot, before Eloquent
     * has its event dispatcher, and a model booted then would never get the
     * listeners that clear this cache. The encrypter is resolved only here, so
     * an install without an APP_KEY yet (`key:generate`) still boots.
     *
     * @param  array<string, string>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function decrypt(array $rows): array
    {
        $saved = [];

        foreach ($rows as $key => $ciphertext) {
            try {
                $payload = json_decode(Crypt::decryptString($ciphertext), true);
                $saved[$key] = is_array($payload) ? $payload : [];
            } catch (DecryptException $e) {
                // Encrypted with another APP_KEY (add the old key to
                // APP_PREVIOUS_KEYS). Skip the group rather than fail every
                // request.
                report($e);
            }
        }

        return $saved;
    }
}
