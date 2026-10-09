<script lang="ts" setup>
// Auto-recharge add-on: when the message balance drops below a threshold,
// Stripe charges the saved card for another block of messages. The dialog
// turns it on or off and sets the threshold and the block size.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useForm } from '@inertiajs/vue3';
import { CheckCircle, CreditCard, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { handleNumericKeyPress } from '../../numericInput';

const props = defineProps<{
    autoRecharge: Domain.Billing.Data.Resources.Subscription.BillingAutoRechargeData;
}>();

const emit = defineEmits<{
    refresh: [];
}>();

const { t } = useI18n();

const isAutoRechargeDialogOpen = ref(false);
const autoRechargeForm = useForm({
    enabled: props.autoRecharge.enabled,
    threshold: props.autoRecharge.threshold,
    amount: props.autoRecharge.amount,
});

watch(
    () => props.autoRecharge,
    (next) => {
        autoRechargeForm.enabled = next.enabled;
        autoRechargeForm.threshold = next.threshold;
        autoRechargeForm.amount = next.amount;
    },
);

// Each recharge buys whole blocks of 1,000 messages at €10 a block.
const autoRechargeUnitPrice = 10;
const autoRechargeUnits = computed(() => Math.max(1, Math.ceil(autoRechargeForm.amount / 1000)));
const autoRechargeChargeEstimate = computed(() => (autoRechargeUnits.value * autoRechargeUnitPrice).toFixed(2));

const saveAutoRecharge = (): void => {
    autoRechargeForm.post(route('billing.addons.auto-recharge.update'), {
        preserveScroll: true,
        onSuccess: () => {
            isAutoRechargeDialogOpen.value = false;
            emit('refresh');
        },
    });
};
</script>

<template>
    <div class="bg-muted/30 hover:bg-muted/50 flex flex-col gap-3 rounded-lg border p-4 transition-colors">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2">
                <div class="bg-background flex h-9 w-9 items-center justify-center rounded-md border">
                    <CreditCard class="text-muted-foreground h-4 w-4" />
                </div>
                <h3 class="text-sm font-semibold">{{ t('billing.addons.auto_recharge.title') }}</h3>
            </div>
            <Badge v-if="autoRecharge.enabled" variant="default" class="gap-1">
                <CheckCircle class="h-3 w-3" />
                {{ t('billing.addons.auto_recharge.on') }}
            </Badge>
            <Badge v-else variant="outline">{{ t('billing.addons.auto_recharge.off') }}</Badge>
        </div>
        <p class="text-muted-foreground text-xs leading-relaxed">
            {{
                t('billing.addons.auto_recharge.summary', {
                    amount: autoRecharge.amount.toLocaleString(),
                    threshold: autoRecharge.threshold.toLocaleString(),
                })
            }}
        </p>
        <div class="mt-auto flex items-end justify-between pt-1">
            <div>
                <span class="text-lg font-bold">€10</span>
                <span class="text-muted-foreground text-xs"> / 1,000</span>
            </div>
            <Dialog v-model:open="isAutoRechargeDialogOpen">
                <DialogTrigger as-child>
                    <Button variant="outline" size="sm">{{
                        autoRecharge.enabled ? t('billing.addons.auto_recharge.manage') : t('billing.addons.auto_recharge.enable')
                    }}</Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <CreditCard class="text-primary h-5 w-5" />
                            {{ t('billing.addons.auto_recharge.dialog_title') }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ t('billing.addons.auto_recharge.dialog_description') }}
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 py-4">
                        <div class="flex items-center justify-between rounded-lg border p-3">
                            <Label for="auto-recharge-enabled" class="flex-1 cursor-pointer">
                                <div class="font-medium">{{ t('billing.addons.auto_recharge.enable_label') }}</div>
                                <div class="text-muted-foreground text-xs">{{ t('billing.addons.auto_recharge.enable_hint') }}</div>
                            </Label>
                            <Switch id="auto-recharge-enabled" v-model="autoRechargeForm.enabled" />
                        </div>
                        <div class="space-y-2">
                            <Label for="auto-recharge-threshold">{{ t('billing.addons.auto_recharge.threshold_label') }}</Label>
                            <Input
                                id="auto-recharge-threshold"
                                v-model.number="autoRechargeForm.threshold"
                                type="number"
                                min="100"
                                max="100000"
                                step="100"
                                :disabled="!autoRechargeForm.enabled"
                                @keydown="handleNumericKeyPress"
                            />
                            <p class="text-muted-foreground text-xs">{{ t('billing.addons.auto_recharge.threshold_hint') }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="auto-recharge-amount">{{ t('billing.addons.auto_recharge.amount_label') }}</Label>
                            <Input
                                id="auto-recharge-amount"
                                v-model.number="autoRechargeForm.amount"
                                type="number"
                                min="1000"
                                max="100000"
                                step="1000"
                                :disabled="!autoRechargeForm.enabled"
                                @keydown="handleNumericKeyPress"
                            />
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium">{{ t('billing.addons.auto_recharge.each_recharge') }}</span>
                                <span class="font-bold">€{{ autoRechargeChargeEstimate }}</span>
                            </div>
                            <p class="text-muted-foreground mt-1 text-xs">
                                {{ t('billing.addons.auto_recharge.charged_note') }}
                            </p>
                        </div>
                    </div>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="isAutoRechargeDialogOpen = false">{{ t('billing.common.cancel') }}</Button>
                        <Button :disabled="autoRechargeForm.processing" class="gap-2" @click="saveAutoRecharge">
                            <Loader2 v-if="autoRechargeForm.processing" class="h-4 w-4 animate-spin" />
                            {{ t('billing.addons.auto_recharge.save') }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
