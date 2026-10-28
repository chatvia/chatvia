<script setup lang="ts">
/**
 * Confirms removing a knowledge source (file, website, text, …) from an agent.
 *
 * A source can be shared by several agents of the workspace. When it is
 * (`agentsCount > 1`) the dialog offers two ways out: unlink it from this agent
 * only, or delete it everywhere. Otherwise it offers a plain delete.
 *
 * The buttons only emit `unlink` / `delete`; the parent runs the request and
 * closes the dialog when it is done, so the spinner stays visible meanwhile.
 * They are plain Buttons, not AlertDialogAction, because AlertDialogAction
 * closes the dialog before its click handler runs.
 */
import { AlertDialog, AlertDialogContent, AlertDialogDescription, AlertDialogHeader, AlertDialogTitle } from '@/shared/ui/alert-dialog';
import { Button } from '@/shared/ui/button';
import { LoaderCircle, Trash2, Unlink } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        title: string;
        /** How many agents use the source; above one it counts as shared. */
        agentsCount?: number;
        /** Shown when the source is not shared. */
        description: string;
        /** Shared sources read "{prefix} <N agents> {suffix}". */
        sharedPrefix: string;
        sharedSuffix: string;
        isUnlinking?: boolean;
        isDeleting?: boolean;
    }>(),
    { agentsCount: 0, isUnlinking: false, isDeleting: false },
);

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    unlink: [];
    delete: [];
}>();

const { t } = useI18n();

const isShared = computed(() => props.agentsCount > 1);
const isBusy = computed(() => props.isUnlinking || props.isDeleting);
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent class="max-w-2xl">
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>
                    <span v-if="isShared">
                        {{ sharedPrefix }}
                        <strong>{{ t('agents.sources.common.agents_count', { count: agentsCount }) }}</strong>
                        {{ sharedSuffix }}
                    </span>
                    <span v-else>{{ description }}</span>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <div class="mt-4 flex items-center justify-end gap-2">
                <Button variant="outline" :disabled="isBusy" @click="open = false">
                    {{ t('agents.sources.common.cancel') }}
                </Button>
                <Button
                    v-if="isShared"
                    variant="secondary"
                    :disabled="isBusy"
                    class="bg-orange-500 text-white hover:bg-orange-600 disabled:opacity-50"
                    @click="emit('unlink')"
                >
                    <LoaderCircle v-if="isUnlinking" class="me-2 h-4 w-4 animate-spin" />
                    <Unlink v-else class="me-2 h-4 w-4" />
                    {{ isUnlinking ? t('agents.sources.common.unlinking') : t('agents.sources.common.unlink') }}
                </Button>
                <Button variant="destructive" :disabled="isBusy" @click="emit('delete')">
                    <LoaderCircle v-if="isDeleting" class="me-2 h-4 w-4 animate-spin" />
                    <Trash2 v-else class="me-2 h-4 w-4" />
                    <span v-if="isDeleting">{{ t('agents.sources.common.deleting') }}</span>
                    <span v-else>{{ isShared ? t('agents.sources.common.delete_permanently') : t('agents.sources.common.delete') }}</span>
                </Button>
            </div>
        </AlertDialogContent>
    </AlertDialog>
</template>
