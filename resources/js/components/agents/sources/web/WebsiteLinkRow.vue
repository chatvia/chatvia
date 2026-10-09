<script setup lang="ts">
/**
 * One link in the "all links" dialog: checkbox, URL, crawl date, and on the
 * right its training state — a check mark just after training, a spinner while
 * pending or training, a retry button after a failure, otherwise a retrain
 * button — followed by a delete button.
 */
import SourceActionButton from '@/components/agents/sources/SourceActionButton.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { WebsiteLink } from '@/composables/agents/sources/web/useWebSources';
import { useDateFormat } from '@/composables/useDateFormat';
import { AlertCircle, CheckCircle2, ExternalLink, LoaderCircle, RefreshCw, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    link: WebsiteLink;
    selected: boolean;
    /** The link finished training a moment ago. */
    justTrained: boolean;
}>();

const emit = defineEmits<{
    toggle: [checked: boolean | 'indeterminate'];
    retrain: [];
    delete: [];
}>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();
</script>

<template>
    <div class="bg-background hover:bg-muted/50 flex items-start gap-2 rounded border p-3 transition-colors">
        <Checkbox
            :id="`dialog-link-${link.id}`"
            :model-value="selected"
            class="data-[state=checked]:bg-primary data-[state=checked]:border-primary mt-0.5 flex-shrink-0"
            @update:model-value="(checked) => emit('toggle', checked)"
        />
        <ExternalLink class="text-muted-foreground mt-0.5 h-3 w-3 flex-shrink-0" />
        <div class="min-w-0 flex-1">
            <a :href="link.url" target="_blank" class="text-foreground hover:text-primary block text-sm break-words hover:underline" @click.stop>
                {{ link.url }}
            </a>
            <p v-if="link.crawled_at" class="text-muted-foreground mt-1 text-xs">
                {{ t('agents.sources.web.crawled_at', { date: formatDateTime(link.crawled_at) }) }}
            </p>
        </div>
        <div class="flex flex-shrink-0 items-center gap-1">
            <TooltipProvider v-if="justTrained">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <div class="flex h-7 w-7 items-center justify-center">
                            <CheckCircle2 class="animate-in zoom-in-50 h-4 w-4 text-green-600 duration-300" />
                        </div>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>{{ t('agents.sources.web.training_completed_tooltip') }}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <TooltipProvider v-else-if="link.status === 'pending' || link.status === 'ingesting'">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <div class="flex h-7 w-7 items-center justify-center">
                            <LoaderCircle class="h-3.5 w-3.5 animate-spin text-blue-600" />
                        </div>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>
                            {{ link.status === 'pending' ? t('agents.sources.web.training_pending') : t('agents.sources.web.training_in_progress') }}
                        </p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <SourceActionButton
                v-else-if="link.status === 'failed'"
                :icon="AlertCircle"
                icon-class="h-3.5 w-3.5"
                :label="t('agents.sources.web.training_failed_retry')"
                class="h-7 w-7 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                @click="emit('retrain')"
            />
            <SourceActionButton
                v-else
                :icon="RefreshCw"
                icon-class="h-3.5 w-3.5"
                :label="t('agents.sources.web.retrain_this_link')"
                class="h-7 w-7 p-0 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                @click="emit('retrain')"
            />
            <SourceActionButton
                :icon="Trash2"
                icon-class="h-3.5 w-3.5"
                :label="t('agents.sources.web.delete_this_link')"
                class="text-destructive hover:text-destructive/80 h-7 w-7 p-0 hover:bg-red-50"
                @click="emit('delete')"
            />
        </div>
    </div>
</template>
