<?php

namespace Domain\Widget\Actions;

use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AppearanceTextResolver;
use Domain\Billing\Enums\CapabilityEnum;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Widget\Data\Settings\WidgetSettingsBrandingData;
use Domain\Widget\Data\Settings\WidgetSettingsResourceData;
use Illuminate\Support\Collection;

/**
 * Assemble the settings payload the embedded widget loads at boot —
 * appearance + widget chrome + starters, plus the "Powered by Chatvia"
 * branding block.
 */
class BuildWidgetSettingsAction
{
    public function __construct(private readonly AppearanceTextResolver $appearanceText) {}

    public function execute(Agent $agent, ?string $visitorLocale = null): WidgetSettingsResourceData
    {
        // Single round-trip for all settings groups + starters; previously
        // ran separate SELECTs on every widget boot.
        $agent->loadMissing(['starterSet', 'team.owner']);
        $agent->loadSettingsGroups(['widget', 'appearance', 'bar', 'surfaces']);

        $widget = $agent->settings('widget');
        $appearance = $agent->settings('appearance');
        $bar = $agent->settings('bar');

        // Resolve text + starters to one effective language: a pinned widget
        // language wins, otherwise the visitor's browser language. Falls back to
        // the base copy when there's no translation for that language.
        $effectiveLocale = ($widget['language'] ?? 'auto') !== 'auto' ? $widget['language'] : $visitorLocale;

        $text = $this->appearanceText->resolve($appearance, $effectiveLocale);

        // The widget resolves its OWN display language client-side (data-language
        // / browser), which can differ from the server's Accept-Language. So we
        // also ship the per-locale maps and let the widget localize the agent
        // strings to that active language. The resolved scalars above stay as the
        // fallback (older cached bundles + languages with no translation).
        $textTranslations = $this->buildTextTranslations($appearance);
        $starters = $this->buildStarters($agent, $effectiveLocale);
        [$bubbleTexts, $bubbleTranslations] = $this->buildBubbles($agent, $effectiveLocale);

        // Workspace-wide: the "remove branding" add-on hides the badge across
        // every agent. Drives both the legacy branding.enabled flag (so cached
        // widget bundles still obey it) and the dedicated chatvia_branding field.
        $showsBranding = $agent->showsChatviaBranding();

        return new WidgetSettingsResourceData(
            protected: $agent->is_public === false,
            // Per-surface kill switches. The embed reads its own mode (bubble →
            // widget_enabled, bar → bar_enabled) and doesn't mount when its
            // surface is off, so disabling one stops it showing on the host page.
            widget_enabled: $agent->surfaceEnabled(ConversationSource::Widget),
            bar_enabled: $agent->surfaceEnabled(ConversationSource::Bar),
            title: (string) ($text['chat_window_title'] ?? ''),
            logo_url: (string) ($appearance['logo'] ?? ''),
            avatar_url: $appearance['avatar'] ?? null,
            welcome_message: $text['welcome_message'] ?? null,
            color_theme: $appearance['color_theme'] ?? null,
            color_shade: $appearance['color_shade'] ?? null,
            bubbles: $bubbleTexts,
            bubble_translations: $bubbleTranslations,
            position: $widget['position'] ?? null,
            show_on_load: $widget['show_on_load'] ?? null,
            show_on_load_delay: $widget['show_on_load_delay'] ?? null,
            branding: new WidgetSettingsBrandingData(
                enabled: $showsBranding,
                name: 'Powered by ChatVia.ai',
                url: 'https://chatvia.ai',
                logo_url: asset('images/logo.svg'),
            ),
            chatvia_branding: $showsBranding,
            starters: $starters,
            text_translations: $textTranslations,
            window_size: $widget['window_size'] ?? null,
            theme: $widget['theme'] ?? null,
            keyboard_trigger: $bar['trigger'] ?? null,
            bar_placement: $bar['placement'] ?? null,
            composer_placeholder: $text['composer_placeholder'] ?? null,
            privacy_policy_url: $appearance['privacy_policy_url'] ?? null,
            handoff_enabled: ($widget['handoff_enabled'] ?? true) && ($agent->team?->hasCapability(CapabilityEnum::HANDOFF) ?? false),
            allow_file_uploads: (bool) ($appearance['allow_file_uploads'] ?? true),
            allow_voice_input: (bool) ($appearance['allow_voice_input'] ?? true),
            max_files_per_chat: (int) config('chatvia.agent.files.upload.max_files'),
        );
    }

    /**
     * Per-locale maps for the three appearance strings, keyed by the widget's
     * payload field names (title / welcome_message / composer_placeholder). Each
     * map holds the effective text per locale (a manual override winning over the
     * AI translation). The widget resolves these to its active language.
     *
     * @param  Collection<string, mixed>  $appearance
     * @return array<string, array<string, string>>
     */
    private function buildTextTranslations(Collection $appearance): array
    {
        $translations = (array) ($appearance['text_translations'] ?? []);
        $overrides = (array) ($appearance['text_overrides'] ?? []);

        $fields = [
            'chat_window_title' => 'title',
            'welcome_message' => 'welcome_message',
            'composer_placeholder' => 'composer_placeholder',
        ];

        $out = [];

        foreach ($fields as $source => $target) {
            $locales = array_unique(array_merge(
                array_keys($translations[$source] ?? []),
                array_keys($overrides[$source] ?? []),
            ));

            foreach ($locales as $locale) {
                $value = $overrides[$source][$locale] ?? $translations[$source][$locale] ?? null;

                if (is_string($value) && $value !== '') {
                    $out[$target][(string) $locale] = $value;
                }
            }
        }

        return $out;
    }

    /**
     * Starters for the widget payload: the base text resolved for the visitor's
     * language (fallback for older bundles) + icon + the per-locale map the
     * current widget resolves client-side to its active language.
     *
     * @return array<int, array{text: string, icon: string|null, translations: array<string, string>}>
     */
    private function buildStarters(Agent $agent, ?string $locale): array
    {
        $resolved = $agent->resolvedStartersForLocale($locale);
        $raw = array_values($agent->resolvedStarters());

        $starters = [];

        foreach ($raw as $i => $starter) {
            $translations = (array) ($starter['translations'] ?? []);
            $overrides = (array) ($starter['overrides'] ?? []);

            $map = [];

            foreach (array_unique(array_merge(array_keys($translations), array_keys($overrides))) as $loc) {
                $value = $overrides[$loc] ?? $translations[$loc] ?? null;

                if (is_string($value) && $value !== '') {
                    $map[(string) $loc] = $value;
                }
            }

            $starters[] = [
                'text' => $resolved[$i]['text'] ?? (string) ($starter['text'] ?? ''),
                'icon' => $starter['icon'] ?? null,
                'translations' => $map,
            ];
        }

        return $starters;
    }

    /**
     * Floating message bubbles for the widget payload. `bubbles` stays a plain
     * string[] (resolved for the visitor's language) so older cached bundles —
     * which render it as text — keep working; the parallel `bubble_translations`
     * array (same index) lets the current widget localize each bubble to its
     * active language. Empty bubbles are dropped from BOTH so indexes align.
     *
     * @return array{0: array<int, string>, 1: array<int, array<string, string>>}
     */
    private function buildBubbles(Agent $agent, ?string $locale): array
    {
        $loc = $locale !== null ? strtolower(trim($locale)) : '';
        $loc = ($loc === '' || $loc === 'auto') ? null : substr($loc, 0, 2);

        $texts = [];
        $maps = [];

        foreach ($agent->normalizedBubbles() as $bubble) {
            $base = (string) ($bubble['text'] ?? '');
            $translations = (array) ($bubble['translations'] ?? []);
            $overrides = (array) ($bubble['overrides'] ?? []);

            $resolved = $base;

            if ($loc !== null) {
                $override = $overrides[$loc] ?? null;
                $translation = $translations[$loc] ?? null;

                $resolved = match (true) {
                    is_string($override) && $override !== '' => $override,
                    is_string($translation) && $translation !== '' => $translation,
                    default => $base,
                };
            }

            if ($resolved === '') {
                continue;
            }

            $map = [];

            foreach (array_unique(array_merge(array_keys($translations), array_keys($overrides))) as $code) {
                $value = $overrides[$code] ?? $translations[$code] ?? null;

                if (is_string($value) && $value !== '') {
                    $map[(string) $code] = $value;
                }
            }

            $texts[] = $resolved;
            $maps[] = $map;
        }

        return [$texts, $maps];
    }
}
