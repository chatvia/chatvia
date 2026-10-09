<script lang="ts" setup>
// A monthly add-on billed per unit (extra agents, custom domains, members).
// Subscribing or changing the quantity goes through Stripe Checkout with a
// full-page redirect; cancelling asks for confirmation first and then posts to
// the add-on's cancel route. Needs an active subscription.
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { CheckCircle, Loader2, type LucideIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { handleNumericKeyPress } from '../../numericInput';
import type { RecurringAddonLabels } from '../../types';

const props = defineProps<{
    addon: Domain.Billing.Data.Resources.Subscription.BillingAddonSubscriptionData;
    subscribed: boolean;
    icon: LucideIcon;
    labels: RecurringAddonLabels;
    /** The price shown on the card, e.g. "€5". */
    price: string;
    /** Monthly euros per unit, for the cost preview in the dialog. */
    unitPrice: number;
    maxQuantity: number;
    inputId: string;
    /** Shown before the quantity in the active badge ("+" for "+3"). */
    badgePrefix: string;
    checkoutRoute: string;
    cancelRoute: string;
}>();

const emit = defineEmits<{
    refresh: [];
}>();

const { t } = useI18n();

const isDialogOpen = ref(false);
const quantity = ref(Math.max(1, props.addon.quantity || 1));
const isSubscribing = ref(false);
const total = computed(() => (quantity.value * props.unitPrice).toFixed(2));

watch(
    () => props.addon.quantity,
    (qty) => {
        if (qty > 0) {
            quantity.value = qty;
        }
    },
);

const subscribe = (): void => {
    isSubscribing.value = true;
    window.location.href = route(props.checkoutRoute, { quantity: quantity.value });
};

const cancelForm = useForm({});
const cancel = (): void => {
    cancelForm.post(route(props.cancelRoute), {
        preserveScroll: true,
        onSuccess: () => emit('refresh'),
    });
};
</script>

<template>
    <div class="bg-muted/30 hover:bg-muted/50 flex flex-col gap-3 rounded-lg border p-4 transition-colors">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2">
                <div class="bg-background flex h-9 w-9 items-center justify-center rounded-md border">
                    <component :is="icon" class="text-muted-foreground h-4 w-4" />
                </div>
                <h3 class="text-sm font-semibold">{{ labels.title }}</h3>
            </div>
            <Badge v-if="addon.active" variant="default" class="gap-1">
                <CheckCircle class="h-3 w-3" />
                {{ badgePrefix }}{{ addon.quantity }}
            </Badge>
            <Badge v-else variant="outline">{{ t('billing.addons.common.none') }}</Badge>
        </div>
        <p class="text-muted-foreground text-xs leading-relaxed">{{ labels.summary }}</p>
        <p v-if="addon.cancels_at" class="text-xs text-orange-600 dark:text-orange-400">
            {{ t('billing.addons.common.ends', { date: addon.cancels_at }) }}
        </p>
        <div class="mt-auto flex items-end justify-between gap-2 pt-1">
            <div>
                <span class="text-lg font-bold">{{ price }}</span>
                <span class="text-muted-foreground text-xs">{{ labels.perUnit }}</span>
            </div>
            <div class="flex items-center gap-1">
                <AlertDialog v-if="addon.active">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="sm" class="text-destructive h-8 px-2">{{ t('billing.common.cancel') }}</Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{ labels.cancelTitle }}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {{ labels.cancelDescription }}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>{{ t('billing.addons.common.keep_subscription') }}</AlertDialogCancel>
                            <AlertDialogAction @click="cancel">{{ t('billing.addons.common.yes_cancel') }}</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
                <Dialog v-model:open="isDialogOpen">
                    <DialogTrigger as-child>
                        <Button variant="outline" size="sm" :disabled="!subscribed">
                            {{ addon.active ? t('billing.addons.common.change') : t('billing.addons.common.subscribe') }}
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle class="flex items-center gap-2">
                                <component :is="icon" class="text-primary h-5 w-5" />
                                {{ addon.active ? labels.changeTitle : labels.subscribeTitle }}
                            </DialogTitle>
                            <DialogDescription>
                                {{ labels.dialogDescription }}
                            </DialogDescription>
                        </DialogHeader>
                        <div class="space-y-4 py-4">
                            <div class="space-y-2">
                                <Label :for="inputId">{{ labels.quantityLabel }}</Label>
                                <Input
                                    :id="inputId"
                                    v-model.number="quantity"
                                    type="number"
                                    min="1"
                                    :max="maxQuantity"
                                    @keydown="handleNumericKeyPress"
                                />
                            </div>
                            <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-900">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ t('billing.addons.common.monthly_cost') }}</span>
                                    <span class="text-primary font-bold">€{{ total }}</span>
                                </div>
                            </div>
                        </div>
                        <DialogFooter class="gap-2">
                            <Button variant="outline" @click="isDialogOpen = false">{{ t('billing.common.cancel') }}</Button>
                            <Button :disabled="isSubscribing || quantity < 1" class="gap-2" @click="subscribe">
                                <Loader2 v-if="isSubscribing" class="h-4 w-4 animate-spin" />
                                {{ addon.active ? t('billing.addons.common.update_quantity') : t('billing.addons.common.subscribe') }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </div>
</template>
