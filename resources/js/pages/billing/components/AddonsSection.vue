<script lang="ts" setup>
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
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useForm } from '@inertiajs/vue3';
import { Bot, CheckCircle, CreditCard, Globe, Loader2, MessageCircle, Sparkles, Zap } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface AutoRecharge {
    enabled: boolean;
    threshold: number;
    amount: number;
    last_triggered_at: string | null;
}

interface MessageCreditsState {
    manual_messages: number;
    auto_recharge_messages: number;
}

interface AddonSubscriptionState {
    active: boolean;
    quantity: number;
    cancels_at: string | null;
}

interface AddonsData {
    auto_recharge: AutoRecharge;
    extra_message_credits: MessageCreditsState;
    extra_agents: AddonSubscriptionState;
    custom_domains: AddonSubscriptionState;
}

const props = defineProps<{
    addons: AddonsData;
    subscribed: boolean;
}>();

const emit = defineEmits<{
    refresh: [];
}>();

// --- Extra Message Credits (one-time purchase) ---
const isCreditsDialogOpen = ref(false);
const creditsQuantity = ref(1);
const isPurchasing = ref(false);
const extraMessagesUnitPrice = 9;
const totalCreditsPrice = computed(() => (creditsQuantity.value * extraMessagesUnitPrice).toFixed(2));

const purchaseExtraMessages = (): void => {
    isPurchasing.value = true;
    window.location.href = route('billing.addons.extra-message-credits', { quantity: creditsQuantity.value });
};

const totalExtraCredits = computed(
    () => props.addons.extra_message_credits.manual_messages + props.addons.extra_message_credits.auto_recharge_messages,
);

// --- Auto Recharge ---
const isAutoRechargeDialogOpen = ref(false);
const autoRechargeForm = useForm({
    enabled: props.addons.auto_recharge.enabled,
    threshold: props.addons.auto_recharge.threshold,
    amount: props.addons.auto_recharge.amount,
});

watch(
    () => props.addons.auto_recharge,
    (next) => {
        autoRechargeForm.enabled = next.enabled;
        autoRechargeForm.threshold = next.threshold;
        autoRechargeForm.amount = next.amount;
    },
);

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

// --- Extra AI Agents (recurring subscription) ---
const isExtraAgentsDialogOpen = ref(false);
const extraAgentsQuantity = ref(Math.max(1, props.addons.extra_agents.quantity || 1));
const isSubscribingExtraAgents = ref(false);
const extraAgentsUnitPrice = 5;
const extraAgentsTotal = computed(() => (extraAgentsQuantity.value * extraAgentsUnitPrice).toFixed(2));

watch(
    () => props.addons.extra_agents.quantity,
    (qty) => {
        if (qty > 0) {
            extraAgentsQuantity.value = qty;
        }
    },
);

const subscribeExtraAgents = (): void => {
    isSubscribingExtraAgents.value = true;
    window.location.href = route('billing.addons.extra-agents', { quantity: extraAgentsQuantity.value });
};

const cancelExtraAgentsForm = useForm({});
const cancelExtraAgents = (): void => {
    cancelExtraAgentsForm.post(route('billing.addons.extra-agents.cancel'), {
        preserveScroll: true,
        onSuccess: () => emit('refresh'),
    });
};

// --- Custom Domains (recurring subscription) ---
const isCustomDomainsDialogOpen = ref(false);
const customDomainsQuantity = ref(Math.max(1, props.addons.custom_domains.quantity || 1));
const isSubscribingCustomDomains = ref(false);
const customDomainsUnitPrice = 29;
const customDomainsTotal = computed(() => (customDomainsQuantity.value * customDomainsUnitPrice).toFixed(2));

watch(
    () => props.addons.custom_domains.quantity,
    (qty) => {
        if (qty > 0) {
            customDomainsQuantity.value = qty;
        }
    },
);

const subscribeCustomDomains = (): void => {
    isSubscribingCustomDomains.value = true;
    window.location.href = route('billing.addons.custom-domains', { quantity: customDomainsQuantity.value });
};

const cancelCustomDomainsForm = useForm({});
const cancelCustomDomains = (): void => {
    cancelCustomDomainsForm.post(route('billing.addons.custom-domains.cancel'), {
        preserveScroll: true,
        onSuccess: () => emit('refresh'),
    });
};

const handleNumericKeyPress = (event: KeyboardEvent): void => {
    if (
        [8, 9, 27, 13, 46].indexOf(event.keyCode) !== -1 ||
        (event.keyCode === 65 && event.ctrlKey === true) ||
        (event.keyCode === 67 && event.ctrlKey === true) ||
        (event.keyCode === 86 && event.ctrlKey === true) ||
        (event.keyCode === 88 && event.ctrlKey === true)
    ) {
        return;
    }
    if ((event.shiftKey || event.keyCode < 48 || event.keyCode > 57) && (event.keyCode < 96 || event.keyCode > 105)) {
        event.preventDefault();
    }
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Sparkles class="text-primary h-5 w-5" />
                Add-ons
            </CardTitle>
            <CardDescription>Extra credits, agents, and domains on top of your plan</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Auto Recharge Credits -->
                <div class="bg-muted/30 hover:bg-muted/50 flex flex-col gap-3 rounded-lg border p-4 transition-colors">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="bg-background flex h-9 w-9 items-center justify-center rounded-md border">
                                <CreditCard class="text-muted-foreground h-4 w-4" />
                            </div>
                            <h3 class="text-sm font-semibold">Auto Recharge</h3>
                        </div>
                        <Badge v-if="addons.auto_recharge.enabled" variant="default" class="gap-1">
                            <CheckCircle class="h-3 w-3" />
                            On
                        </Badge>
                        <Badge v-else variant="outline">Off</Badge>
                    </div>
                    <p class="text-muted-foreground text-xs leading-relaxed">
                        Auto-buy {{ addons.auto_recharge.amount.toLocaleString() }} credits when balance drops below
                        {{ addons.auto_recharge.threshold.toLocaleString() }}.
                    </p>
                    <div class="mt-auto flex items-end justify-between pt-1">
                        <div>
                            <span class="text-lg font-bold">$10</span>
                            <span class="text-muted-foreground text-xs"> / 1,000</span>
                        </div>
                        <Dialog v-model:open="isAutoRechargeDialogOpen">
                            <DialogTrigger as-child>
                                <Button variant="outline" size="sm">{{ addons.auto_recharge.enabled ? 'Manage' : 'Enable' }}</Button>
                            </DialogTrigger>
                            <DialogContent class="sm:max-w-md">
                                <DialogHeader>
                                    <DialogTitle class="flex items-center gap-2">
                                        <CreditCard class="text-primary h-5 w-5" />
                                        Auto Recharge Settings
                                    </DialogTitle>
                                    <DialogDescription>
                                        When your remaining credits drop to or below the threshold, we'll automatically charge your default payment
                                        method and top up your balance.
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="space-y-4 py-4">
                                    <div class="flex items-center justify-between rounded-lg border p-3">
                                        <Label for="auto-recharge-enabled" class="flex-1 cursor-pointer">
                                            <div class="font-medium">Enable Auto Recharge</div>
                                            <div class="text-muted-foreground text-xs">Charge automatically when credits run low</div>
                                        </Label>
                                        <Switch id="auto-recharge-enabled" v-model="autoRechargeForm.enabled" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="auto-recharge-threshold">Threshold (credits)</Label>
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
                                        <p class="text-muted-foreground text-xs">Recharge fires when your balance drops to or below this amount.</p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="auto-recharge-amount">Top-up amount (credits)</Label>
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
                                            <span class="font-medium">Each recharge:</span>
                                            <span class="font-bold">${{ autoRechargeChargeEstimate }}</span>
                                        </div>
                                        <p class="text-muted-foreground mt-1 text-xs">
                                            Charged immediately to your default payment method when triggered.
                                        </p>
                                    </div>
                                </div>
                                <DialogFooter class="gap-2">
                                    <Button variant="outline" @click="isAutoRechargeDialogOpen = false">Cancel</Button>
                                    <Button :disabled="autoRechargeForm.processing" class="gap-2" @click="saveAutoRecharge">
                                        <Loader2 v-if="autoRechargeForm.processing" class="h-4 w-4 animate-spin" />
                                        Save Settings
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <!-- Extra Message Credits -->
                <div class="bg-muted/30 hover:bg-muted/50 flex flex-col gap-3 rounded-lg border p-4 transition-colors">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="bg-background flex h-9 w-9 items-center justify-center rounded-md border">
                                <MessageCircle class="text-muted-foreground h-4 w-4" />
                            </div>
                            <h3 class="text-sm font-semibold">Extra Credits</h3>
                        </div>
                        <Badge v-if="totalExtraCredits > 0" variant="default" class="gap-1">
                            <Zap class="h-3 w-3" />
                            {{ totalExtraCredits.toLocaleString() }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground text-xs leading-relaxed">
                        One-time top-up. Each bundle adds 1,000 messages that expire after 30 days.
                    </p>
                    <div class="mt-auto flex items-end justify-between pt-1">
                        <div>
                            <span class="text-lg font-bold">$9</span>
                            <span class="text-muted-foreground text-xs"> / 1,000</span>
                        </div>
                        <Dialog v-model:open="isCreditsDialogOpen">
                            <DialogTrigger as-child>
                                <Button variant="outline" size="sm">Buy Credits</Button>
                            </DialogTrigger>
                            <DialogContent class="sm:max-w-md">
                                <DialogHeader>
                                    <DialogTitle class="flex items-center gap-2">
                                        <Zap class="text-primary h-5 w-5" />
                                        Purchase Message Credits
                                    </DialogTitle>
                                    <DialogDescription>
                                        Each bundle includes 1,000 messages that must be consumed within one month of purchase.
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="space-y-4 py-4">
                                    <div class="space-y-2">
                                        <Label for="credits-quantity">Quantity (1,000 messages per bundle)</Label>
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
                                            <span class="font-medium">Total Messages:</span>
                                            <span class="font-bold">{{ (creditsQuantity * 1000).toLocaleString() }}</span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="font-medium">Total Price:</span>
                                            <span class="text-primary font-bold">${{ totalCreditsPrice }}</span>
                                        </div>
                                    </div>
                                </div>
                                <DialogFooter class="gap-2">
                                    <Button variant="outline" @click="isCreditsDialogOpen = false">Cancel</Button>
                                    <Button :disabled="isPurchasing || creditsQuantity < 1" class="gap-2" @click="purchaseExtraMessages">
                                        <Loader2 v-if="isPurchasing" class="h-4 w-4 animate-spin" />
                                        <CreditCard v-else class="h-4 w-4" />
                                        {{ isPurchasing ? 'Processing...' : `Purchase for $${totalCreditsPrice}` }}
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                <!-- Extra AI Agents -->
                <div class="bg-muted/30 hover:bg-muted/50 flex flex-col gap-3 rounded-lg border p-4 transition-colors">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="bg-background flex h-9 w-9 items-center justify-center rounded-md border">
                                <Bot class="text-muted-foreground h-4 w-4" />
                            </div>
                            <h3 class="text-sm font-semibold">Extra Agents</h3>
                        </div>
                        <Badge v-if="addons.extra_agents.active" variant="default" class="gap-1">
                            <CheckCircle class="h-3 w-3" />
                            +{{ addons.extra_agents.quantity }}
                        </Badge>
                        <Badge v-else variant="outline">None</Badge>
                    </div>
                    <p class="text-muted-foreground text-xs leading-relaxed">Add more AI agents beyond your plan limit.</p>
                    <p v-if="addons.extra_agents.cancels_at" class="text-xs text-orange-600 dark:text-orange-400">
                        Ends {{ addons.extra_agents.cancels_at }}
                    </p>
                    <div class="mt-auto flex items-end justify-between gap-2 pt-1">
                        <div>
                            <span class="text-lg font-bold">$5</span>
                            <span class="text-muted-foreground text-xs"> / agent</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <AlertDialog v-if="addons.extra_agents.active">
                                <AlertDialogTrigger as-child>
                                    <Button variant="ghost" size="sm" class="text-destructive h-8 px-2">Cancel</Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Cancel Extra Agents subscription?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Your extra agents quota will be removed immediately and you won't be charged again.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Keep subscription</AlertDialogCancel>
                                        <AlertDialogAction @click="cancelExtraAgents">Yes, cancel</AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                            <Dialog v-model:open="isExtraAgentsDialogOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline" size="sm" :disabled="!subscribed">
                                        {{ addons.extra_agents.active ? 'Change' : 'Subscribe' }}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle class="flex items-center gap-2">
                                            <Bot class="text-primary h-5 w-5" />
                                            {{ addons.extra_agents.active ? 'Change Extra Agents' : 'Subscribe to Extra Agents' }}
                                        </DialogTitle>
                                        <DialogDescription>
                                            Add additional AI agents on top of your plan. Billed monthly at $5 per agent.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div class="space-y-4 py-4">
                                        <div class="space-y-2">
                                            <Label for="extra-agents-quantity">Number of extra agents</Label>
                                            <Input
                                                id="extra-agents-quantity"
                                                v-model.number="extraAgentsQuantity"
                                                type="number"
                                                min="1"
                                                max="100"
                                                @keydown="handleNumericKeyPress"
                                            />
                                        </div>
                                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-900">
                                            <div class="flex items-center justify-between">
                                                <span class="font-medium">Monthly cost:</span>
                                                <span class="text-primary font-bold">${{ extraAgentsTotal }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <DialogFooter class="gap-2">
                                        <Button variant="outline" @click="isExtraAgentsDialogOpen = false">Cancel</Button>
                                        <Button
                                            :disabled="isSubscribingExtraAgents || extraAgentsQuantity < 1"
                                            class="gap-2"
                                            @click="subscribeExtraAgents"
                                        >
                                            <Loader2 v-if="isSubscribingExtraAgents" class="h-4 w-4 animate-spin" />
                                            {{ addons.extra_agents.active ? 'Update Quantity' : 'Subscribe' }}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                </div>

                <!-- Custom Domain -->
                <div class="bg-muted/30 hover:bg-muted/50 flex flex-col gap-3 rounded-lg border p-4 transition-colors">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <div class="bg-background flex h-9 w-9 items-center justify-center rounded-md border">
                                <Globe class="text-muted-foreground h-4 w-4" />
                            </div>
                            <h3 class="text-sm font-semibold">Custom Domain</h3>
                        </div>
                        <Badge v-if="addons.custom_domains.active" variant="default" class="gap-1">
                            <CheckCircle class="h-3 w-3" />
                            {{ addons.custom_domains.quantity }}
                        </Badge>
                        <Badge v-else variant="outline">None</Badge>
                    </div>
                    <p class="text-muted-foreground text-xs leading-relaxed">Use your own custom domain for AI agents.</p>
                    <p v-if="addons.custom_domains.cancels_at" class="text-xs text-orange-600 dark:text-orange-400">
                        Ends {{ addons.custom_domains.cancels_at }}
                    </p>
                    <div class="mt-auto flex items-end justify-between gap-2 pt-1">
                        <div>
                            <span class="text-lg font-bold">$29</span>
                            <span class="text-muted-foreground text-xs"> / domain</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <AlertDialog v-if="addons.custom_domains.active">
                                <AlertDialogTrigger as-child>
                                    <Button variant="ghost" size="sm" class="text-destructive h-8 px-2">Cancel</Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Cancel Custom Domain subscription?</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Your custom domain quota will be removed immediately and you won't be charged again.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Keep subscription</AlertDialogCancel>
                                        <AlertDialogAction @click="cancelCustomDomains">Yes, cancel</AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                            <Dialog v-model:open="isCustomDomainsDialogOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline" size="sm" :disabled="!subscribed">
                                        {{ addons.custom_domains.active ? 'Change' : 'Subscribe' }}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle class="flex items-center gap-2">
                                            <Globe class="text-primary h-5 w-5" />
                                            {{ addons.custom_domains.active ? 'Change Custom Domains' : 'Subscribe to Custom Domain' }}
                                        </DialogTitle>
                                        <DialogDescription> Use your own domain for AI agents. Billed monthly at $29 per domain. </DialogDescription>
                                    </DialogHeader>
                                    <div class="space-y-4 py-4">
                                        <div class="space-y-2">
                                            <Label for="custom-domains-quantity">Number of domains</Label>
                                            <Input
                                                id="custom-domains-quantity"
                                                v-model.number="customDomainsQuantity"
                                                type="number"
                                                min="1"
                                                max="50"
                                                @keydown="handleNumericKeyPress"
                                            />
                                        </div>
                                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-900">
                                            <div class="flex items-center justify-between">
                                                <span class="font-medium">Monthly cost:</span>
                                                <span class="text-primary font-bold">${{ customDomainsTotal }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <DialogFooter class="gap-2">
                                        <Button variant="outline" @click="isCustomDomainsDialogOpen = false">Cancel</Button>
                                        <Button
                                            :disabled="isSubscribingCustomDomains || customDomainsQuantity < 1"
                                            class="gap-2"
                                            @click="subscribeCustomDomains"
                                        >
                                            <Loader2 v-if="isSubscribingCustomDomains" class="h-4 w-4 animate-spin" />
                                            {{ addons.custom_domains.active ? 'Update Quantity' : 'Subscribe' }}
                                        </Button>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="!subscribed" class="text-muted-foreground mt-4 text-center text-xs">
                Extra Agents and Custom Domain require an active main subscription.
            </p>
        </CardContent>
    </Card>
</template>
