<script setup lang="ts">
// Block the person behind an abusive trigger. A chatter's account is turned
// off (they can be told by email); an anonymous visitor is blocked by their
// fingerprint for a while. The reason is for the team only.
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { block } from '@/routes/agents/guardrails';
import { useForm } from '@inertiajs/vue3';
import { Ban, LoaderCircle } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { GuardrailTrigger } from './types';

const props = defineProps<{
    agentId: string;
    trigger: GuardrailTrigger | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const DURATIONS = ['day', 'week', 'month', 'forever'] as const;
// Literal keys, so the i18n check sees them.
const DURATION_LABELS: Record<(typeof DURATIONS)[number], string> = {
    day: 'agents.guardrails.block.durations.day',
    week: 'agents.guardrails.block.durations.week',
    month: 'agents.guardrails.block.durations.month',
    forever: 'agents.guardrails.block.durations.forever',
};

const form = useForm({
    duration: 'week' as (typeof DURATIONS)[number],
    reason: '',
    notify: true,
});

const isChatter = computed(() => props.trigger?.offender?.kind === 'chatter');
const name = computed(() => props.trigger?.user_name ?? '');

// Each opening starts from the defaults.
watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

const submit = () => {
    if (!props.trigger) return;

    form.post(block.url({ agent: props.agentId, event: props.trigger.external_id }), {
        preserveScroll: true,
        // Keep the Activity tab and the open trigger; the rows reload.
        preserveState: true,
        onSuccess: () => {
            open.value = false;
            toast.success(isChatter.value ? t('agents.guardrails.block.chatter_blocked') : t('agents.guardrails.block.visitor_blocked'));
        },
        onError: (errors) => toast.error(Object.values(errors)[0] ?? t('agents.guardrails.block.error')),
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    isChatter ? t('agents.guardrails.block.chatter_title', { name }) : t('agents.guardrails.block.visitor_title')
                }}</DialogTitle>
                <DialogDescription>{{
                    isChatter ? t('agents.guardrails.block.chatter_description') : t('agents.guardrails.block.visitor_description')
                }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div v-if="!isChatter" class="space-y-1.5">
                    <Label for="block-duration">{{ t('agents.guardrails.block.duration') }}</Label>
                    <Select v-model="form.duration">
                        <SelectTrigger id="block-duration" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="duration in DURATIONS" :key="duration" :value="duration">{{
                                t(DURATION_LABELS[duration])
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-1.5">
                    <Label for="block-reason">{{ t('agents.guardrails.block.reason') }}</Label>
                    <Input id="block-reason" v-model="form.reason" maxlength="255" :placeholder="t('agents.guardrails.block.reason_placeholder')" />
                    <p class="text-muted-foreground text-xs">{{ t('agents.guardrails.block.reason_help') }}</p>
                </div>

                <label v-if="isChatter" class="flex cursor-pointer items-start gap-2 text-sm">
                    <Checkbox v-model="form.notify" class="mt-0.5" />
                    <span>{{ t('agents.guardrails.block.notify', { name }) }}</span>
                </label>

                <p v-else class="text-muted-foreground bg-muted/40 rounded-md p-3 text-xs leading-relaxed">
                    {{ t('agents.guardrails.block.visitor_limits') }}
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">{{ t('agents.guardrails.cancel') }}</Button>
                <Button variant="destructive" :disabled="form.processing" @click="submit">
                    <LoaderCircle v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                    <Ban v-else class="me-2 h-4 w-4" />
                    {{ isChatter ? t('agents.guardrails.block.block_chatter') : t('agents.guardrails.block.block_visitor') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
