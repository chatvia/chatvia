<?php

namespace Domain\Widget\Actions;

use App\Identity\Workspace\Services\WorkspaceActivation;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\AppearanceTextResolver;
use Domain\Agent\Services\OfferedModels;
use Domain\Agent\ValueObjects\TranslatableText;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Services\Voice\VoiceAvailability;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Services\ChatFileModel;
use Domain\Widget\Data\Settings\WidgetSettingsBrandingData;
use Domain\Widget\Data\Settings\WidgetSettingsData;
use Domain\Widget\Data\Settings\WidgetSettingsReverbData;
use Illuminate\Support\Collection;

/**
 * Assembles the settings payload the embedded widget loads at boot:
 * appearance, widget and bar behaviour, starters, bubbles and the
 * "Powered by …" credit.
 *
 * The agent's texts (title, welcome message, placeholder, starters, bubbles)
 * are translatable. Each one is sent twice: resolved for one language (the
 * widget's pinned language, else the visitor's browser language) for older
 * cached widget bundles, and as a per-locale map the current widget resolves
 * itself, because it picks its display language client-side. In every map a
 * manual override wins over the AI translation ({@see TranslatableText}).
 */
class BuildWidgetSettingsAction
{
    /** Widget payload field for each translatable appearance key. */
    private const array TEXT_FIELDS = [
        'chat_window_title' => 'title',
        'welcome_message' => 'welcome_message',
        'composer_placeholder' => 'composer_placeholder',
        'notice' => 'notice',
    ];

    /** The widget `language` setting value that means "follow the visitor". */
    private const string FOLLOW_VISITOR_LANGUAGE = 'auto';

    public function __construct(
        private readonly AppearanceTextResolver $appearanceText,
        private readonly ChatFileModel $fileModel,
        private readonly VoiceAvailability $voice,
        private readonly OfferedModels $offeredModels,
        private readonly Transcriber $transcriber,
        private readonly WorkspaceActivation $activation,
    ) {}

    public function execute(Agent $agent, ?string $visitorLocale = null): WidgetSettingsData
    {
        // One round-trip for every settings group and the starters.
        $agent->loadMissing('starterSet');
        $agent->loadSettingsGroups(['widget', 'appearance', 'bar', 'surfaces']);

        $widget = $agent->settings('widget');
        $appearance = $agent->settings('appearance');
        $bar = $agent->settings('bar');

        $locale = $this->effectiveLocale($widget, $visitorLocale);
        $text = $this->appearanceText->resolve($appearance, $locale);
        [$bubbles, $bubbleTranslations] = $this->bubbles($agent, $locale);
        $showsBranding = $this->showsBranding();
        $isActive = $this->activation->isActive($agent->workspace_id);

        return new WidgetSettingsData(
            protected: $agent->is_public === false,
            // Per-surface switches: the embed does not mount when its own
            // surface (bubble → widget, bar → bar) is turned off, or at all
            // while the workspace is deactivated.
            widget_enabled: $isActive && $agent->surfaceEnabled(ConversationSource::Widget),
            bar_enabled: $isActive && $agent->surfaceEnabled(ConversationSource::Bar),
            title: (string) ($text['chat_window_title'] ?? ''),
            logo_url: (string) ($appearance['logo'] ?? ''),
            avatar_url: $appearance['avatar'] ?? null,
            welcome_message: $text['welcome_message'] ?? null,
            color_theme: $appearance['color_theme'] ?? null,
            color_shade: $appearance['color_shade'] ?? null,
            bubbles: $bubbles,
            bubble_translations: $bubbleTranslations,
            position: $widget['position'] ?? null,
            show_on_load: $widget['show_on_load'] ?? null,
            show_on_load_delay: $widget['show_on_load_delay'] ?? null,
            branding: $this->branding($showsBranding),
            chatvia_branding: $showsBranding,
            starters: $this->starters($agent, $locale),
            text_translations: $this->textTranslations($appearance),
            window_size: $widget['window_size'] ?? null,
            theme: $widget['theme'] ?? null,
            keyboard_trigger: $bar['trigger'] ?? null,
            bar_placement: $bar['placement'] ?? null,
            composer_placeholder: $text['composer_placeholder'] ?? null,
            notice: filled($text['notice'] ?? null) ? $text['notice'] : null,
            privacy_policy_url: $appearance['privacy_policy_url'] ?? null,
            sources_display: $agent->sourcesDisplay(),
            handoff_enabled: (bool) ($widget['handoff_enabled'] ?? true),
            ai_disclosure: (bool) config('chatvia.widget.ai_disclosure', true),
            // Uploads need a model that reads files; it decides the types.
            allow_file_uploads: (bool) ($appearance['allow_file_uploads'] ?? true) && $this->fileModel->isAvailable(),
            upload_accept: $this->fileModel->acceptedMimeTypes(),
            // Dictation is written down by the server: no speech-to-text, no mic.
            allow_voice_input: (bool) ($appearance['allow_voice_input'] ?? true) && $this->transcriber->isEnabled(),
            voice_conversation: $this->voice->isOnFor($agent),
            max_files_per_chat: (int) config('chatvia.agent.files.upload.max_files'),
            reverb: $this->reverb(),
            models: $this->offeredModels->for($agent),
        );
    }

    /**
     * The Reverb address browsers connect to: the app key the dashboard pages
     * also get, and the public host, port and scheme from
     * `chatvia.widget.reverb` (null = the widget API's own server).
     */
    private function reverb(): WidgetSettingsReverbData
    {
        $public = (array) config('chatvia.widget.reverb');
        $port = $public['port'] ?? null;

        return new WidgetSettingsReverbData(
            key: ((string) config('broadcasting.connections.reverb.key')) ?: null,
            host: ((string) ($public['host'] ?? '')) ?: null,
            port: is_numeric($port) ? (int) $port : null,
            scheme: ((string) ($public['scheme'] ?? '')) ?: null,
        );
    }

    /**
     * A language pinned in the widget settings wins; otherwise the visitor's
     * browser language. Null means "no translation, use the base text".
     *
     * @param  Collection<string, mixed>  $widget
     */
    private function effectiveLocale(Collection $widget, ?string $visitorLocale): ?string
    {
        $pinned = $widget['language'] ?? self::FOLLOW_VISITOR_LANGUAGE;

        return $pinned !== self::FOLLOW_VISITOR_LANGUAGE ? $pinned : $visitorLocale;
    }

    /**
     * Whether the "Powered by" credit shows: the install's switch (Settings →
     * Brand) decides for every agent.
     */
    private function showsBranding(): bool
    {
        return config('chatvia.powered_by') === true;
    }

    /**
     * The credit's label and its link to chatvia.ai are fixed here and in
     * the widget, never a setting. The `enabled` flag mirrors
     * `chatvia_branding` so cached widget bundles that only read this block
     * still obey it.
     */
    private function branding(bool $enabled): WidgetSettingsBrandingData
    {
        return new WidgetSettingsBrandingData(
            enabled: $enabled,
            name: 'Powered by ChatVia.ai',
            url: 'https://chatvia.ai',
            logo_url: asset('images/logo.svg'),
        );
    }

    /**
     * Per-locale maps for the three appearance texts, keyed by the widget's
     * field names: `{ title: { de: "…" }, welcome_message: {…}, … }`.
     *
     * @param  Collection<string, mixed>  $appearance
     * @return array<string, array<string, string>>
     */
    private function textTranslations(Collection $appearance): array
    {
        $translations = (array) ($appearance['text_translations'] ?? []);
        $overrides = (array) ($appearance['text_overrides'] ?? []);

        $maps = [];

        foreach (self::TEXT_FIELDS as $key => $field) {
            $map = $this->localeMap(TranslatableText::fromStored([
                'translations' => $translations[$key] ?? [],
                'overrides' => $overrides[$key] ?? [],
            ]));

            if ($map !== []) {
                $maps[$field] = $map;
            }
        }

        return $maps;
    }

    /**
     * Starters with their text resolved for `$locale`, their icon and their
     * per-locale map.
     *
     * @return array<int, array{text: string, icon: string|null, translations: array<string, string>}>
     */
    private function starters(Agent $agent, ?string $locale): array
    {
        $resolved = $agent->resolvedStartersForLocale($locale);
        $starters = [];

        foreach (array_values($agent->resolvedStarters()) as $index => $starter) {
            $starters[] = [
                'text' => $resolved[$index]['text'] ?? (string) ($starter['text'] ?? ''),
                'icon' => $starter['icon'] ?? null,
                'translations' => $this->localeMap(TranslatableText::fromStored($starter)),
            ];
        }

        return $starters;
    }

    /**
     * The floating bubbles as two parallel lists: the texts resolved for
     * `$locale` (a plain string list, which older cached bundles render) and
     * the per-locale map of each bubble at the same index. A bubble whose
     * resolved text is empty is left out of both, so the indexes stay aligned.
     *
     * @return array{0: list<string>, 1: list<array<string, string>>}
     */
    private function bubbles(Agent $agent, ?string $locale): array
    {
        $texts = [];
        $maps = [];

        foreach ($agent->normalizedBubbles() as $bubble) {
            $copy = TranslatableText::fromStored($bubble);
            $text = $copy->in($locale) ?? '';

            if ($text === '') {
                continue;
            }

            $texts[] = $text;
            $maps[] = $this->localeMap($copy);
        }

        return [$texts, $maps];
    }

    /**
     * Every locale that has a translation or a manual edit, with its text.
     * The manual edit wins whenever one is stored for a locale (even an empty
     * one, which leaves the locale out); empty texts are left out.
     *
     * @return array<string, string>
     */
    private function localeMap(TranslatableText $copy): array
    {
        $map = [];

        foreach (array_unique([...array_keys($copy->translations), ...array_keys($copy->overrides)]) as $locale) {
            $text = $copy->overrides[$locale] ?? $copy->translations[$locale] ?? null;

            if (filled($text)) {
                $map[(string) $locale] = $text;
            }
        }

        return $map;
    }
}
