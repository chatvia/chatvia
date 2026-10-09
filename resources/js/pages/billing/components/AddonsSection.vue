<script lang="ts" setup>
// The add-ons card on the billing page: auto-recharge, extra message credits
// and the monthly add-ons (extra agents, custom domains, remove branding,
// members). Each card handles its own checkout or cancel and asks the page to
// reload the billing data afterwards.
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Bot, Globe, Sparkles, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { RecurringAddonLabels } from '../types';
import AutoRechargeAddonCard from './addons/AutoRechargeAddonCard.vue';
import MessageCreditsAddonCard from './addons/MessageCreditsAddonCard.vue';
import RecurringAddonCard from './addons/RecurringAddonCard.vue';
import RemoveBrandingAddonCard from './addons/RemoveBrandingAddonCard.vue';

defineProps<{
    addons: Domain.Billing.Data.Resources.Subscription.BillingAddonsData;
    subscribed: boolean;
}>();

const emit = defineEmits<{
    refresh: [];
}>();

const { t } = useI18n();

const extraAgentsLabels = computed<RecurringAddonLabels>(() => ({
    title: t('billing.addons.extra_agents.title'),
    summary: t('billing.addons.extra_agents.summary'),
    perUnit: t('billing.addons.per_agent'),
    cancelTitle: t('billing.addons.extra_agents.cancel_title'),
    cancelDescription: t('billing.addons.extra_agents.cancel_description'),
    changeTitle: t('billing.addons.extra_agents.change_title'),
    subscribeTitle: t('billing.addons.extra_agents.subscribe_title'),
    dialogDescription: t('billing.addons.extra_agents.dialog_description'),
    quantityLabel: t('billing.addons.extra_agents.quantity_label'),
}));

const customDomainsLabels = computed<RecurringAddonLabels>(() => ({
    title: t('billing.addons.custom_domains.title'),
    summary: t('billing.addons.custom_domains.summary'),
    perUnit: t('billing.addons.per_domain'),
    cancelTitle: t('billing.addons.custom_domains.cancel_title'),
    cancelDescription: t('billing.addons.custom_domains.cancel_description'),
    changeTitle: t('billing.addons.custom_domains.change_title'),
    subscribeTitle: t('billing.addons.custom_domains.subscribe_title'),
    dialogDescription: t('billing.addons.custom_domains.dialog_description'),
    quantityLabel: t('billing.addons.custom_domains.quantity_label'),
}));

const membersLabels = computed<RecurringAddonLabels>(() => ({
    title: t('billing.addons.members.title'),
    summary: t('billing.addons.members.summary'),
    perUnit: t('billing.addons.per_member'),
    cancelTitle: t('billing.addons.members.cancel_title'),
    cancelDescription: t('billing.addons.members.cancel_description'),
    changeTitle: t('billing.addons.members.change_title'),
    subscribeTitle: t('billing.addons.members.subscribe_title'),
    dialogDescription: t('billing.addons.members.dialog_description'),
    quantityLabel: t('billing.addons.members.quantity_label'),
}));
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Sparkles class="text-primary h-5 w-5" />
                {{ t('billing.addons.title') }}
            </CardTitle>
            <CardDescription>{{ t('billing.addons.description') }}</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="grid gap-4 sm:grid-cols-2">
                <AutoRechargeAddonCard :auto-recharge="addons.auto_recharge" @refresh="emit('refresh')" />

                <MessageCreditsAddonCard :credits="addons.extra_message_credits" />

                <RecurringAddonCard
                    :addon="addons.extra_agents"
                    :subscribed="subscribed"
                    :icon="Bot"
                    :labels="extraAgentsLabels"
                    price="€5"
                    :unit-price="5"
                    :max-quantity="100"
                    input-id="extra-agents-quantity"
                    badge-prefix="+"
                    checkout-route="billing.addons.extra-agents"
                    cancel-route="billing.addons.extra-agents.cancel"
                    @refresh="emit('refresh')"
                />

                <RecurringAddonCard
                    :addon="addons.custom_domains"
                    :subscribed="subscribed"
                    :icon="Globe"
                    :labels="customDomainsLabels"
                    price="€29"
                    :unit-price="29"
                    :max-quantity="50"
                    input-id="custom-domains-quantity"
                    badge-prefix=""
                    checkout-route="billing.addons.custom-domains"
                    cancel-route="billing.addons.custom-domains.cancel"
                    @refresh="emit('refresh')"
                />

                <RemoveBrandingAddonCard :addon="addons.remove_branding" :subscribed="subscribed" @refresh="emit('refresh')" />

                <RecurringAddonCard
                    :addon="addons.members"
                    :subscribed="subscribed"
                    :icon="Users"
                    :labels="membersLabels"
                    price="€9"
                    :unit-price="9"
                    :max-quantity="200"
                    input-id="members-quantity"
                    badge-prefix="+"
                    checkout-route="billing.addons.members"
                    cancel-route="billing.addons.members.cancel"
                    @refresh="emit('refresh')"
                />
            </div>

            <p v-if="!subscribed" class="text-muted-foreground mt-4 text-center text-xs">
                {{ t('billing.addons.requires_subscription') }}
            </p>
        </CardContent>
    </Card>
</template>
