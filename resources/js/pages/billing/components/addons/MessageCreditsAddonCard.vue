<script lang="ts" setup>
// Extra message credits: a one-time purchase of message blocks through Stripe
// Checkout. The badge shows the credits left from manual and auto-recharge
// purchases together.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CreditCard, Loader2, MessageCircle, Zap } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { handleNumericKeyPress } from '../../numericInput';

const props = defineProps<{
    credits: Domain.Billing.Data.Resources.Subscription.BillingAddonMessageCreditsData;
}>();

const { t } = useI18n();

const isCreditsDialogOpen = ref(false);
const creditsQuantity = ref(1);
const isPurchasing = ref(false);
const extraMessagesUnitPrice = 9;
const totalCreditsPrice = computed(() => (creditsQuantity.value * extraMessagesUnitPrice).toFixed(2));

const totalExtraCredits = computed(() => props.credits.manual_messages + props.credits.auto_recharge_messages);

// A full-page redirect, not an Inertia visit: the route sends the browser on to Stripe Checkout.
const purchaseExtraMessages = (): void => {
    isPurchasing.value = true;
    window.location.href = route('billing.addons.extra-message-credits', { quantity: creditsQuantity.value });
};
</script>

<template>
    <div class="bg-muted/30 hover:bg-muted/50 flex flex-col gap-3 rounded-lg border p-4 transition-colors">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2">
                <div class="bg-background flex h-9 w-9 items-center justify-center rounded-md border">
                    <MessageCircle class="text-muted-foreground h-4 w-4" />
                </div>
                <h3 class="text-sm font-semibold">{{ t('billing.addons.extra_credits.title') }}</h3>
            </div>
            <Badge v-if="totalExtraCredits > 0" variant="default" class="gap-1">
                <Zap class="h-3 w-3" />
                {{ totalExtraCredits.toLocaleString() }}
            </Badge>
        </div>
        <p class="text-muted-foreground text-xs leading-relaxed">
            {{ t('billing.addons.extra_credits.summary') }}
        </p>
        <div class="mt-auto flex items-end justify-between pt-1">
            <div>
                <span class="text-lg font-bold">€9</span>
                <span class="text-muted-foreground text-xs"> / 1,000</span>
            </div>
            <Dialog v-model:open="isCreditsDialogOpen">
                <DialogTrigger as-child>
                    <Button variant="outline" size="sm">{{ t('billing.addons.extra_credits.buy') }}</Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Zap class="text-primary h-5 w-5" />
                            {{ t('billing.addons.extra_credits.dialog_title') }}
                        </DialogTitle>
                        <DialogDescription>
                            {{ t('billing.addons.extra_credits.dialog_description') }}
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <Label for="credits-quantity">{{ t('billing.addons.extra_credits.quantity_label') }}</Label>
                            <Input
                                id="credits-quantity"
                                v-model.number="creditsQuantity"
                                type="number"
                                min="1"
                                max="100"
                                @keydown="handleNumericKeyPress"
                            />
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-900">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ t('billing.addons.extra_credits.total_messages') }}</span>
                                <span class="font-bold">{{ (creditsQuantity * 1000).toLocaleString() }}</span>
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="font-medium">{{ t('billing.addons.extra_credits.total_price') }}</span>
                                <span class="text-primary font-bold">€{{ totalCreditsPrice }}</span>
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="gap-2">
                        <Button variant="outline" @click="isCreditsDialogOpen = false">{{ t('billing.common.cancel') }}</Button>
                        <Button :disabled="isPurchasing || creditsQuantity < 1" class="gap-2" @click="purchaseExtraMessages">
                            <Loader2 v-if="isPurchasing" class="h-4 w-4 animate-spin" />
                            <CreditCard v-else class="h-4 w-4" />
                            {{
                                isPurchasing
                                    ? t('billing.common.processing')
                                    : t('billing.addons.extra_credits.purchase_for', { amount: totalCreditsPrice })
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
