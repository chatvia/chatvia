/**
 * On/off state of an agent's three surfaces (Hosted Chat, Widget, Command Bar)
 * on the Deploy page. Each surface is on unless it was saved as `false`. A
 * toggle saves all three (settings group `surfaces`) and is rolled back in the
 * UI when the request fails.
 */
import type { SurfaceSettings } from '@/components/agents/deploy/installSnippets';
import { router } from '@inertiajs/vue3';
import { type MaybeRefOrGetter, reactive, ref, toValue } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export type SurfaceKey = 'hosted_chat' | 'widget' | 'bar';

export function useSurfaceToggles(agentId: MaybeRefOrGetter<string>, surfaces: SurfaceSettings) {
    const { t } = useI18n();

    const surfaceEnabled = reactive<Record<SurfaceKey, boolean>>({
        hosted_chat: surfaces?.hosted_chat !== false,
        widget: surfaces?.widget !== false,
        bar: surfaces?.bar !== false,
    });
    const savingSurfaces = ref(false);

    function toggleSurface(key: SurfaceKey, value: boolean): void {
        surfaceEnabled[key] = value;
        savingSurfaces.value = true;
        router.post(
            route('agents.settings.update', { agent: toValue(agentId), group: 'surfaces' }),
            { ...surfaceEnabled },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success(value ? t('agents.deploy.surface_enabled') : t('agents.deploy.surface_disabled')),
                onError: () => {
                    surfaceEnabled[key] = !value;
                    toast.error(t('agents.deploy.surface_error'));
                },
                onFinish: () => (savingSurfaces.value = false),
            },
        );
    }

    return { surfaceEnabled, savingSurfaces, toggleSurface };
}
