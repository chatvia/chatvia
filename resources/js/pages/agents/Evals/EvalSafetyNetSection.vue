<script setup lang="ts">
// The "Safety net" section of the Evals page: the switch that runs the evals
// a while after the agent changes (and warns when a case that passed now
// fails), and which knowledge sources the last finished run drew on, with
// the names of the ones no case reaches yet.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { autoRun as autoRunRoute } from '@/routes/agents/evals';
import { router } from '@inertiajs/vue3';
import { Sparkles } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agentExternalId: string;
    autoRun: boolean;
    coverage: Domain.Evaluation.Data.Resources.EvalSourceCoverageData | null;
    canManage: boolean;
}>();

const emit = defineEmits<{
    fromKnowledge: [];
}>();

const { t } = useI18n();

const enabled = ref(props.autoRun);
const isSaving = ref(false);

watch(
    () => props.autoRun,
    (value) => {
        enabled.value = value;
    },
);

function save(value: boolean) {
    enabled.value = value;
    isSaving.value = true;
    router.put(
        autoRunRoute.url(props.agentExternalId),
        { auto_run: value },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                enabled.value = !value;
            },
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
}
</script>

<template>
    <section class="space-y-3">
        <div>
            <h2 class="text-base font-semibold">{{ t('agents.evals.safety.title') }}</h2>
            <p class="text-muted-foreground text-sm">{{ t('agents.evals.safety.description') }}</p>
        </div>

        <div class="bg-card divide-y rounded-lg border">
            <div class="flex items-start justify-between gap-4 p-4">
                <div class="space-y-1">
                    <Label for="eval-auto-run" class="text-sm font-medium">{{ t('agents.evals.safety.auto_run') }}</Label>
                    <p class="text-muted-foreground text-xs">{{ t('agents.evals.safety.auto_run_help') }}</p>
                </div>
                <Switch id="eval-auto-run" :model-value="enabled" :disabled="!canManage || isSaving" @update:model-value="save" />
            </div>

            <div class="space-y-3 p-4">
                <p class="text-sm font-medium">{{ t('agents.evals.safety.coverage') }}</p>
                <p v-if="coverage === null" class="text-muted-foreground text-sm">{{ t('agents.evals.safety.coverage_none') }}</p>
                <template v-else>
                    <p class="text-muted-foreground text-sm">
                        {{ t('agents.evals.safety.coverage_count', { tested: coverage.tested, total: coverage.total }) }}
                    </p>
                    <div v-if="coverage.untested.length > 0" class="space-y-2">
                        <p class="text-muted-foreground text-xs">{{ t('agents.evals.safety.untested') }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="name in coverage.untested" :key="name" variant="outline" dir="auto" class="max-w-64 truncate font-normal">
                                {{ name }}
                            </Badge>
                        </div>
                        <Button v-if="canManage" size="sm" variant="outline" @click="emit('fromKnowledge')">
                            <Sparkles class="me-2 h-4 w-4" />
                            {{ t('agents.evals.safety.add_cases') }}
                        </Button>
                    </div>
                </template>
            </div>
        </div>
    </section>
</template>
