<script lang="ts" setup>
// The payment methods page: saved cards, the billing contact and address, and
// dialogs to add a card (Stripe Elements) or edit the billing information.
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CreditCard, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AddPaymentMethodDialog from './components/AddPaymentMethodDialog.vue';
import BillingInformationCard from './components/BillingInformationCard.vue';
import BillingInformationDialog from './components/BillingInformationDialog.vue';
import PaymentMethodList from './components/PaymentMethodList.vue';
import type { BillingUser, PaymentMethod, SetupIntent } from './types';

defineProps<{
    intent: SetupIntent;
    paymentMethods: PaymentMethod[];
    stripeKey: string;
    user: BillingUser;
}>();

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('billing.common.billing'),
        href: route('billing.index'),
    },
    {
        title: t('billing.payment_methods.breadcrumb'),
        href: route('billing.payment-methods'),
    },
];

const showAddPaymentDialog = ref(false);
const showBillingDialog = ref(false);
</script>

<template>
    <AppLayout :title="t('billing.payment_methods.page_title')" :breadcrumbs="breadcrumbs">
        <Head :title="t('billing.payment_methods.page_title')" />

        <div class="container mx-auto max-w-5xl py-8">
            <div class="space-y-8">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="flex items-center gap-3 text-3xl font-bold tracking-tight">
                            <CreditCard class="text-primary h-8 w-8" />
                            {{ t('billing.payment_methods.heading') }}
                        </h1>
                        <p class="text-muted-foreground mt-2">{{ t('billing.payment_methods.subheading') }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Button variant="outline" as-child>
                            <Link :href="route('billing.index')">
                                <ArrowLeft class="me-2 h-4 w-4" />
                                {{ t('billing.common.back_to_billing') }}
                            </Link>
                        </Button>
                        <Button @click="showAddPaymentDialog = true" class="gap-2">
                            <Plus class="h-4 w-4" />
                            {{ t('billing.payment_methods.add_method') }}
                        </Button>
                    </div>
                </div>

                <div class="grid gap-6">
                    <PaymentMethodList :payment-methods="paymentMethods" @add="showAddPaymentDialog = true" />

                    <BillingInformationCard :user="user" @edit="showBillingDialog = true" />

                    <!-- Security Notice -->
                    <Card>
                        <CardContent class="pt-6">
                            <div class="flex items-start gap-3">
                                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                                    <CreditCard class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <h4 class="mb-1 font-medium">{{ t('billing.payment_methods.secure_title') }}</h4>
                                    <p class="text-muted-foreground text-sm">
                                        {{ t('billing.payment_methods.secure_description') }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <BillingInformationDialog v-model:open="showBillingDialog" :user="user" />

        <AddPaymentMethodDialog v-model:open="showAddPaymentDialog" :stripe-key="stripeKey" :client-secret="intent.client_secret" />
    </AppLayout>
</template>
