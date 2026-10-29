<?php

declare(strict_types=1);

namespace App\Admin\Issue\Services;

use App\Admin\Clusters\Settings\IconTone;
use App\Admin\Clusters\Settings\Pages\ManageAiProviderSettings;
use App\Admin\Issue\Data\IssueServiceData;
use App\Admin\Settings\AiProviderIcon;
use Domain\Llm\Enums\AiProvider;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

/**
 * The services Radar knows: every AI provider, by its own key, and the
 * others listed in config/radar.php. It names a service and finds which one
 * a call went to by its host. Any other host (a workspace's MCP server, an
 * HTTP tool, a website being read) is not the admin's to fix: no service.
 */
final class IssueServices
{
    public function for(string $key): IssueServiceData
    {
        $provider = AiProvider::tryFrom($key);

        if ($provider !== null) {
            return new IssueServiceData(
                key: $key,
                label: $provider->label(),
                logo: AiProviderIcon::of($provider),
                icon: Heroicon::OutlinedCpuChip,
                tone: IconTone::LOGO,
                settingsUrl: ManageAiProviderSettings::getUrl(),
                keysUrl: $provider->keysUrl(),
                provider: $provider,
            );
        }

        $configured = config("radar.services.{$key}");
        $configured = is_array($configured) ? $configured : [];
        $page = $configured['settings'] ?? null;
        $logo = $configured['logo'] ?? null;
        $icon = $configured['icon'] ?? null;
        $icon = $icon instanceof Heroicon ? $icon : Heroicon::OutlinedServerStack;
        $tone = $configured['tone'] ?? null;

        return new IssueServiceData(
            key: $key,
            label: filled($configured['label'] ?? null) && is_scalar($configured['label']) ? (string) $configured['label'] : Str::headline($key),
            logo: filled($logo) && is_scalar($logo) ? asset((string) $logo) : null,
            // A service no one listed still gets an icon, so every row has one.
            icon: $icon,
            tone: filled($tone) && is_scalar($tone) ? (string) $tone : IconTone::of($icon),
            settingsUrl: filled($page) && is_scalar($page) ? ((string) $page)::getUrl() : null,
            keysUrl: null,
            provider: null,
        );
    }

    /** The key of the service a URL belongs to, or null for a host that is not the admin's. */
    public function forUrl(string $url): ?string
    {
        $host = $this->host($url);

        return blank($host) ? null : $this->hosts()->get($host);
    }

    /**
     * Every known host, by the key of its service.
     *
     * @return Collection<string, string>
     */
    private function hosts(): Collection
    {
        return once(function (): Collection {
            $providers = collect(AiProvider::cases())
                ->flatMap(fn (AiProvider $provider): array => collect($provider->apiHosts())->mapWithKeys(fn (string $host): array => [$host => $provider->value])->all());

            $others = collect(Config::array('radar.services', []))
                ->flatMap(fn (mixed $service, int|string $key): array => collect($this->serviceHosts(is_array($service) ? $service : []))
                    ->mapWithKeys(fn (string $host): array => [$host => (string) $key])
                    ->all());

            return $providers->union($others);
        });
    }

    /**
     * The hosts of a service in config/radar.php: the ones it lists, and the
     * addresses saved in the config keys it names.
     *
     * @param  array<array-key, mixed>  $service
     * @return list<string>
     */
    private function serviceHosts(array $service): array
    {
        return collect(Arr::array($service, 'hosts', []))
            ->merge(collect(Arr::array($service, 'host_config', []))->map(fn (mixed $configKey): string => $this->host((string) config(is_scalar($configKey) ? (string) $configKey : ''))))
            ->map(fn (mixed $host): string => is_scalar($host) ? Str::lower((string) $host) : '')
            ->filter()
            ->values()
            ->all();
    }

    private function host(string $url): string
    {
        return blank($url) ? '' : Str::lower(Uri::of($url)->host() ?? '');
    }
}
