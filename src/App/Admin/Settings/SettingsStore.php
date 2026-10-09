<?php

namespace App\Admin\Settings;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;
use Throwable;

/**
 * Puts the credentials saved in the admin panel into config, so the code that
 * reads `config('services.google.client_id')` and the like gets them with no
 * change. The admin panel is the only source for these keys: a field with
 * nothing saved gets its default (redirect URLs) or null.
 *
 * The rows are cached as ciphertext (any write clears the cache) and decrypted
 * in each process. Long-running queue workers call {@see apply()} before every
 * job; that is one cache read when nothing changed.
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
            foreach ($group->fields() as $field) {
                $value = $saved[$group->value][$field->name] ?? null;

                // Every field is set, also the empty ones: in a worker, a value
                // removed in the panel must not keep its old value.
                $this->config->set($field->config, filled($value) ? $value : $field->defaultValue());
            }
        }

        $this->appliedHash = $hash;
    }

    public function status(SettingGroup $group, ?AdminSetting $setting): SettingStatus
    {
        if ($this->missingFields($group, $setting) === []) {
            return SettingStatus::Configured;
        }

        return $setting?->hasSavedValues() ? SettingStatus::Incomplete : SettingStatus::Missing;
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
            return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => $this->db->connection()
                ->table('admin_settings')
                ->whereNotNull('payload')
                ->pluck('payload', 'key')
                ->all());
        } catch (QueryException) {
            // No table yet (a fresh install before `migrate`) or no database
            // (composer scripts in a build): nothing is configured.
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
                // APP_PREVIOUS_KEYS). Skip the group rather than fail every
                // request.
                report($e);
            }
        }

        return $saved;
    }
}
