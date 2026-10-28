/**
 * Navigation of the Setup page: the surface tabs above the page and the
 * section menu on the left. Some sections only exist on one surface (Chat
 * Bubbles on the Widget), so the menu follows the active surface and falls
 * back to Info when the open section disappears.
 */
import {
    Bot,
    Globe,
    Image as ImageIcon,
    Languages,
    MessageSquare,
    MessagesSquare,
    Search,
    Settings,
    SlidersHorizontal,
    Sparkles,
    Type,
} from 'lucide-vue-next';
import { computed, ref, watch, type Component, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { SetupSurface } from './useSetupPreview';

export type SetupSection = 'info' | 'persona' | 'starters' | 'bubbles' | 'languages' | 'model' | 'branding' | 'behavior' | 'settings';

export interface SetupNavItem<T extends string> {
    value: T;
    label: string;
    icon: Component;
}

export function useSetupSections(surface: Ref<SetupSurface>) {
    const { t } = useI18n();

    const active = ref<SetupSection>('info');

    const surfaceTabs = computed<SetupNavItem<SetupSurface>[]>(() => [
        { value: 'hosted_chat', label: t('agents.setup.surfaces.chat'), icon: MessageSquare },
        { value: 'widget', label: t('agents.setup.surfaces.widget'), icon: Globe },
        { value: 'bar', label: t('agents.setup.surfaces.bar'), icon: Search },
    ]);

    const sections = computed(() => {
        const items: SetupNavItem<SetupSection>[] = [
            { value: 'info', label: t('agents.setup.menu.info'), icon: Type },
            { value: 'persona', label: t('agents.setup.menu.persona'), icon: Bot },
            { value: 'starters', label: t('agents.setup.menu.starters'), icon: MessageSquare },
        ];

        // Chat Bubbles sit next to the widget launcher, so they only show on the
        // Widget tab (right below Starters).
        if (surface.value === 'widget') {
            items.push({ value: 'bubbles', label: t('agents.setup.menu.bubbles'), icon: MessagesSquare });
        }

        // Install snippets live on the separate Deploy page (agents.deploy).
        items.push(
            { value: 'languages', label: t('agents.setup.menu.languages'), icon: Languages },
            { value: 'model', label: t('agents.setup.menu.model'), icon: Sparkles },
            { value: 'branding', label: t('agents.setup.menu.branding'), icon: ImageIcon },
            { value: 'behavior', label: t('agents.setup.menu.behavior'), icon: Settings },
            { value: 'settings', label: t('agents.setup.menu.settings'), icon: SlidersHorizontal },
        );

        return items;
    });

    // Don't leave a section open that the new surface hides.
    watch(surface, (next) => {
        if (next !== 'widget' && active.value === 'bubbles') {
            active.value = 'info';
        }
    });

    return { active, surfaceTabs, sections };
}
