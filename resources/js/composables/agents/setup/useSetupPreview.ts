/**
 * State of the Setup page's live preview: which surface (Hosted Chat, Widget,
 * Command Bar) and device size it shows, the iframe URL for that surface, and a
 * key that reloads the iframe after every save. Also copies the agent id from
 * the preview toolbar.
 */
import { useClipboard } from '@vueuse/core';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export type SetupSurface = 'hosted_chat' | 'widget' | 'bar';
export type PreviewDevice = 'desktop' | 'tablet' | 'mobile';

export const PREVIEW_DEVICES: readonly PreviewDevice[] = ['desktop', 'tablet', 'mobile'];

interface PreviewAgent {
    external_id: string;
    chat_url: string;
}

export function useSetupPreview(agent: () => PreviewAgent, authToken: () => string | null) {
    const { t } = useI18n();

    // The surface tab is preview-only UI state: content settings save once and
    // apply to every surface; only the Behavior section swaps its fields.
    const surface = ref<SetupSurface>('hosted_chat');
    const device = ref<PreviewDevice>('desktop');

    // True while the pane splitter is dragged, so the iframe stops eating the
    // mouse events and the mouse-up lands on the splitter.
    const isResizing = ref(false);

    // Part of the iframe :key — bumping it re-mounts the iframe so it picks up
    // the saved settings. Also bumped by the toolbar's Refresh button.
    const reloadKey = ref(0);
    const reload = (): void => {
        reloadKey.value += 1;
    };

    // A private agent's Hosted Chat needs the dashboard user's auth token.
    const hostedChatUrl = computed(() => {
        const base = agent().chat_url;
        const token = authToken();
        if (!token) return base;
        return `${base}${base.includes('?') ? '&' : '?'}auth_token=${token}`;
    });

    const url = computed(() => {
        if (surface.value === 'hosted_chat') return hostedChatUrl.value;
        if (surface.value === 'widget') {
            return `${route('agents.widget.preview', { agent: agent().external_id })}?full-preview=true`;
        }
        return route('agents.bar.preview', { agent: agent().external_id });
    });

    const frameClass = computed(() => {
        if (device.value === 'tablet') {
            return 'h-[calc(100%-2rem)] max-h-[900px] w-[768px] rounded-xl border shadow-lg overflow-hidden bg-white';
        }
        if (device.value === 'mobile') {
            return 'h-[calc(100%-2rem)] max-h-[800px] w-[414px] rounded-xl border shadow-lg overflow-hidden bg-white';
        }
        return 'h-full w-full';
    });

    const openInNewTab = (): void => {
        if (url.value) window.open(url.value, '_blank');
    };

    // The external id is the public identifier used in embed snippets, routes and the API.
    const { copy, copied: isAgentIdCopied } = useClipboard({ legacy: true, copiedDuring: 2000 });
    const copyAgentId = async (): Promise<void> => {
        try {
            await copy(agent().external_id);
            toast.success(t('agents.setup.toasts.agent_id_copied'));
        } catch {
            toast.error(t('agents.setup.toasts.copy_error'));
        }
    };

    return { surface, device, isResizing, reloadKey, reload, url, frameClass, openInNewTab, isAgentIdCopied, copyAgentId };
}

export type SetupPreview = ReturnType<typeof useSetupPreview>;
