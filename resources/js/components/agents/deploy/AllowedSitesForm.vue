<script setup lang="ts">
/**
 * The widget's allowed sites (its origin allow-list), editable on the Deploy
 * page because it is a deployment and security concern. Only the `sites` key
 * is posted; the settings endpoint merges per key and leaves the rest of the
 * widget settings alone.
 */
import type { SurfaceSettings } from '@/components/agents/deploy/installSnippets';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { update as updateSettings } from '@/routes/agents/settings';
import { useForm } from '@inertiajs/vue3';
import { Globe, Save } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agentId: string;
    widgetSettings: SurfaceSettings;
    defaultWidget: SurfaceSettings;
}>();

const { t } = useI18n();

function initialSites(): string[] {
    const sites = props.widgetSettings?.sites ?? props.defaultWidget?.sites;
    return Array.isArray(sites) ? sites.map(String) : [];
}

const sitesForm = useForm<{ sites: string[] }>({ sites: initialSites() });

// One site per line in the textarea; blank lines are dropped.
const sitesText = computed({
    get: () => sitesForm.sites.join('\n'),
    set: (value: string) => {
        sitesForm.sites = value
            .split(/\n+/)
            .map((site) => site.trim())
            .filter(Boolean);
    },
});

function saveSites(): void {
    sitesForm.post(updateSettings.url({ agent: props.agentId, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('agents.deploy.sites_saved')),
        onError: () => toast.error(t('agents.deploy.sites_error')),
    });
}
</script>

<template>
    <div class="space-y-2 border-t pt-4">
        <Label for="deploy-widget-sites" class="text-sm font-medium">
            {{ t('agents.setup.behavior.allowed_sites') }}
            <span class="text-muted-foreground font-normal">{{ t('agents.setup.behavior.allowed_sites_hint') }}</span>
        </Label>
        <div class="relative">
            <div class="text-muted-foreground pointer-events-none absolute start-0 top-0 flex items-center ps-3 pt-2.5">
                <Globe class="size-4" />
            </div>
            <Textarea
                id="deploy-widget-sites"
                v-model="sitesText"
                rows="4"
                dir="ltr"
                class="min-h-24 ps-9"
                :placeholder="t('agents.setup.behavior.allowed_sites_placeholder')"
            />
        </div>
        <p class="text-muted-foreground text-xs">{{ t('agents.setup.behavior.allowed_sites_help') }}</p>
        <div class="flex justify-end">
            <Button type="button" size="sm" class="gap-2" :disabled="sitesForm.processing" @click="saveSites">
                <Save class="size-4" /> {{ t('agents.deploy.save') }}
            </Button>
        </div>
    </div>
</template>
