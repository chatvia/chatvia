<script setup lang="ts">
/**
 * The "Search knowledge" switch above an agent's sources. Shown as a switch
 * while the admin lets agents turn search off, and as a note when the
 * install does not search at all.
 */
import { errorMessage } from '@/api/http';
import { type KnowledgeSearchState, setKnowledgeSearch } from '@/api/knowledgeSearch';
import { Switch } from '@/components/ui/switch';
import { BookOpen, Info } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agentId: string;
    state: KnowledgeSearchState;
}>();

const { t } = useI18n();

const enabled = ref(props.state.enabled);
const saving = ref(false);

async function toggle(value: boolean): Promise<void> {
    if (saving.value) return;
    saving.value = true;

    try {
        await setKnowledgeSearch(props.agentId, value);
        enabled.value = value;
        toast.success(value ? t('agents.sources.search.saved_on') : t('agents.sources.search.saved_off'));
    } catch (error: unknown) {
        toast.error(errorMessage(error, t('agents.sources.search.save_failed')));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div v-if="!state.installEnabled" class="bg-muted/40 text-muted-foreground mb-4 flex items-start gap-3 rounded-lg border p-4 text-sm">
        <Info class="mt-0.5 h-4 w-4 shrink-0" />
        <p>{{ t('agents.sources.search.install_off') }}</p>
    </div>

    <div v-else-if="state.canChange" class="bg-card mb-4 rounded-lg border p-4">
        <div class="flex items-center gap-4">
            <div class="bg-muted flex h-9 w-9 shrink-0 items-center justify-center rounded-md">
                <BookOpen class="h-4 w-4" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">{{ t('agents.sources.search.title') }}</p>
                <p class="text-muted-foreground text-sm">{{ t('agents.sources.search.description') }}</p>
            </div>
            <Switch :model-value="enabled" :disabled="saving" :aria-label="t('agents.sources.search.title')" @update:model-value="toggle" />
        </div>
        <p v-if="!enabled" class="text-muted-foreground mt-3 flex items-start gap-2 border-t pt-3 text-sm">
            <Info class="mt-0.5 h-4 w-4 shrink-0" />
            {{ t('agents.sources.search.off_note') }}
        </p>
    </div>
</template>
