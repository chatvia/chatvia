<script lang="ts" setup>
// Remove-branding add-on: a flat monthly subscription with no quantity.
// Subscribing redirects to Stripe Checkout; cancelling asks for confirmation.
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
import { useForm } from '@inertiajs/vue3';
import { CheckCircle, Loader2, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    addon: Domain.Billing.Data.Resources.Subscription.BillingAddonSubscriptionData;
    subscribed: boolean;
}>();

const emit = defineEmits<{
    refresh: [];
}>();

const { t } = useI18n();

const isSubscribingRemoveBranding = ref(false);
const subscribeRemoveBranding = (): void => {
    isSubscribingRemoveBranding.value = true;
    window.location.href = route('billing.addons.remove-branding');
};

const cancelRemoveBrandingForm = useForm({});
const cancelRemoveBranding = (): void => {
    cancelRemoveBrandingForm.post(route('billing.addons.remove-branding.cancel'), {
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
                    <Sparkles class="text-muted-foreground h-4 w-4" />
                </div>
                <h3 class="text-sm font-semibold">{{ t('billing.addons.remove_branding.title') }}</h3>
            </div>
            <Badge v-if="addon.active" variant="default" class="gap-1">
                <CheckCircle class="h-3 w-3" />
                {{ t('billing.addons.remove_branding.active') }}
            </Badge>
            <Badge v-else variant="outline">{{ t('billing.addons.common.none') }}</Badge>
        </div>
        <p class="text-muted-foreground text-xs leading-relaxed">
            {{ t('billing.addons.remove_branding.summary') }}
        </p>
        <p v-if="addon.cancels_at" class="text-xs text-orange-600 dark:text-orange-400">
            {{ t('billing.addons.common.ends', { date: addon.cancels_at }) }}
        </p>
        <div class="mt-auto flex items-end justify-between gap-2 pt-1">
            <div>
                <span class="text-lg font-bold">€19</span>
                <span class="text-muted-foreground text-xs">{{ t('billing.addons.per_month') }}</span>
            </div>
            <div class="flex items-center gap-1">
                <AlertDialog v-if="addon.active">
                    <AlertDialogTrigger as-child>
                        <Button variant="ghost" size="sm" class="text-destructive h-8 px-2">{{ t('billing.common.cancel') }}</Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{ t('billing.addons.remove_branding.cancel_title') }}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {{ t('billing.addons.remove_branding.cancel_description') }}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>{{ t('billing.addons.common.keep_subscription') }}</AlertDialogCancel>
                            <AlertDialogAction @click="cancelRemoveBranding">{{ t('billing.addons.common.yes_cancel') }}</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    class="gap-2"
                    :disabled="!subscribed || isSubscribingRemoveBranding"
                    @click="subscribeRemoveBranding"
                >
                    <Loader2 v-if="isSubscribingRemoveBranding" class="h-4 w-4 animate-spin" />
                    {{ t('billing.addons.common.subscribe') }}
                </Button>
            </div>
        </div>
    </div>
</template>
