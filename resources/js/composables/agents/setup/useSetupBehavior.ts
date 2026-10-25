/**
 * The Behavior section, whose fields swap with the preview surface: Hosted
 * Chat page options (`embed` group), Widget launcher options (`widget` group)
 * and Command Bar trigger + placement (`bar` group). Each surface has its own
 * form and Save button. Also builds the option lists for their selects.
 */
import { update as updateSettings } from '@/routes/agents/settings';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { SettingsReader } from './settingValues';
import { submitSetupForm, type SetupSectionDeps } from './submitSetupForm';

export interface SelectOption {
    value: string;
    label: string;
}

type BehaviorGroup = 'embed' | 'widget' | 'bar';

export function useSetupBehavior({ agentId, reloadPreview }: SetupSectionDeps, embed: SettingsReader, widget: SettingsReader, bar: SettingsReader) {
    const { t } = useI18n();

    const saveGroup = (form: Parameters<typeof submitSetupForm>[0], group: BehaviorGroup, saving: Ref<boolean>, successKey: string): void =>
        submitSetupForm(form, 'post', updateSettings.url({ agent: agentId(), group }), {
            saving,
            success: t(successKey),
            error: t('agents.setup.toasts.behaviour_error'),
            onSuccess: reloadPreview,
        });

    // Hosted Chat page options.
    const hostedForm = useForm({
        remove_sidebar: embed.boolean('remove_sidebar'),
        hide_sidebar_on_page_load: embed.boolean('hide_sidebar_on_page_load'),
        enable_theme_switcher: embed.boolean('enable_theme_switcher', true),
        default_theme: embed.string('default_theme', 'auto'),
        enable_chat_expand: embed.boolean('enable_chat_expand', true),
        show_language_switcher: embed.boolean('show_language_switcher', true),
        language: embed.string('language', 'auto'),
    });
    const isSavingHosted = ref(false);
    const saveHosted = (): void => saveGroup(hostedForm, 'embed', isSavingHosted, 'agents.setup.toasts.hosted_saved');

    // Widget launcher options, including the allowed-sites list.
    const widgetForm = useForm({
        position: widget.string('position', 'bottom-right'),
        window_size: widget.string('window_size', 'large'),
        theme: widget.string('theme', 'auto'),
        show_on_load: widget.boolean('show_on_load'),
        show_on_load_delay: widget.number('show_on_load_delay', 500),
        language: widget.string('language', 'auto'),
        sites: widget.list<string>('sites'),
        handoff_enabled: widget.boolean('handoff_enabled', true),
        chat_deletion_mode: widget.string('chat_deletion_mode', 'end'),
    });
    const isSavingWidget = ref(false);
    const saveWidget = (): void => saveGroup(widgetForm, 'widget', isSavingWidget, 'agents.setup.toasts.widget_saved');

    // The sites list is edited as one site per line.
    const sitesText = computed({
        get: () => widgetForm.sites.join('\n'),
        set: (value: string) => {
            widgetForm.sites = value
                .split(/\n+/)
                .map((site) => site.trim())
                .filter(Boolean);
        },
    });

    // Command Bar keyboard trigger + placement.
    const barForm = useForm({
        trigger: bar.string('trigger', 'cmd+k'),
        placement: bar.string('placement', 'top'),
    });
    const isSavingBar = ref(false);
    const saveBar = (): void => saveGroup(barForm, 'bar', isSavingBar, 'agents.setup.toasts.bar_saved');

    return { hostedForm, isSavingHosted, saveHosted, widgetForm, isSavingWidget, saveWidget, sitesText, barForm, isSavingBar, saveBar };
}

export type SetupBehavior = ReturnType<typeof useSetupBehavior>;

/** The labelled choices of the Behavior selects. */
export function useBehaviorOptions() {
    const { t } = useI18n();

    // The stored `cmd+k`-style values are canonical; the widget maps them per
    // visitor. Show the owner the modifier of the keyboard they actually use:
    // Cmd (⌘) on a Mac, Ctrl everywhere else.
    const isMac = typeof navigator !== 'undefined' && navigator.platform.toUpperCase().includes('MAC');
    const modifier = isMac ? '⌘ + ' : 'Ctrl + ';
    const triggerOptions: SelectOption[] = [
        { value: 'cmd+k', label: `${modifier}K` },
        { value: 'cmd+j', label: `${modifier}J` },
        { value: 'cmd+/', label: `${modifier}/` },
        { value: '/', label: '/' },
        { value: 'shift+/', label: '?' },
    ];

    const placementOptions = computed<SelectOption[]>(() => [
        { value: 'top', label: t('agents.setup.options.placement_top') },
        { value: 'top-right', label: t('agents.setup.options.placement_top_right') },
        { value: 'bottom', label: t('agents.setup.options.placement_bottom') },
        { value: 'bottom-right', label: t('agents.setup.options.placement_bottom_right') },
    ]);

    // The widget pins its launcher to the bottom of the viewport (top-* fall
    // back to bottom-right), so only the two bottom corners are offered.
    const positionOptions = computed<SelectOption[]>(() => [
        { value: 'bottom-right', label: t('agents.setup.options.position_bottom_right') },
        { value: 'bottom-left', label: t('agents.setup.options.position_bottom_left') },
    ]);

    const windowSizeOptions = computed<SelectOption[]>(() => [
        { value: 'small', label: t('agents.setup.options.size_small') },
        { value: 'medium', label: t('agents.setup.options.size_medium') },
        { value: 'large', label: t('agents.setup.options.size_large') },
    ]);

    const themeOptions = computed<SelectOption[]>(() => [
        { value: 'auto', label: t('agents.setup.options.theme_auto') },
        { value: 'light', label: t('agents.setup.options.theme_light') },
        { value: 'dark', label: t('agents.setup.options.theme_dark') },
    ]);

    // What a visitor's "remove this chat" does (ChatDeletionMode).
    const chatDeletionOptions = computed<SelectOption[]>(() => [
        { value: 'end', label: t('agents.setup.options.chat_deletion_end') },
        { value: 'hide', label: t('agents.setup.options.chat_deletion_hide') },
        { value: 'delete', label: t('agents.setup.options.chat_deletion_delete') },
    ]);

    return { triggerOptions, placementOptions, positionOptions, windowSizeOptions, themeOptions, chatDeletionOptions };
}

/** A code → name language map as select options, in the map's order. */
export const languageOptions = (languages: Record<string, string>): SelectOption[] =>
    Object.entries(languages).map(([value, label]) => ({ value, label }));
