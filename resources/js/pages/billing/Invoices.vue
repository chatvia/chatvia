<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, DollarSign, Download, FileText, Receipt } from 'lucide-vue-next';

interface Invoice {
    id: string;
    number: string;
    created: number;
    status: string;
    total: number;
    currency: string;
    customer_name: string;
    customer_email: string;
    invoice_pdf: string;
    hosted_invoice_url: string;
    paid: boolean;
}

defineProps<{
    invoices: Invoice[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Billing',
        href: route('billing.index'),
    },
    {
        title: 'Invoices',
        href: route('billing.invoices'),
    },
];

const formatDate = (timestamp: number) => {
    return new Date(timestamp * 1000).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatAmount = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency.toUpperCase(),
    }).format(amount / 100); // Convert from cents to dollars
};

const getStatusBadge = (status: string) => {
    switch (status.toLowerCase()) {
        case 'paid':
            return { variant: 'default', text: 'Paid' };
        case 'pending':
            return { variant: 'secondary', text: 'Pending' };
        case 'failed':
            return { variant: 'destructive', text: 'Failed' };
        default:
            return { variant: 'outline', text: status };
    }
};
</script>

<template>
    <AppLayout title="Invoices" :breadcrumbs="breadcrumbs">
        <Head title="Invoices" />

        <div class="container mx-auto max-w-5xl py-8">
            <div class="space-y-8">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="flex items-center gap-3 text-3xl font-bold tracking-tight">
                            <Receipt class="text-primary h-8 w-8" />
                            Invoices
                        </h1>
                        <p class="text-muted-foreground mt-2">View and download your billing history and invoices</p>
                    </div>
                    <Button variant="outline" as-child>
                        <Link :href="route('billing.index')">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Billing
                        </Link>
                    </Button>
                </div>

                <!-- Invoices List -->
                <Card>
                    <CardHeader>
                        <CardTitle>Billing History</CardTitle>
                        <CardDescription> Download receipts and invoices for your subscription payments </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="invoices.length > 0" class="space-y-4">
                            <div
                                v-for="invoice in invoices"
                                :key="invoice.id"
                                class="hover:bg-muted/50 flex items-center justify-between rounded-lg border p-4 transition-colors"
                            >
                                <div class="flex items-center gap-4">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/20">
                                        <FileText class="h-6 w-6 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-medium">Invoice #{{ invoice.number }}</p>
                                            <Badge :variant="invoice.paid ? 'default' : 'secondary'" class="text-xs">
                                                {{ invoice.paid ? 'Paid' : 'Pending' }}
                                            </Badge>
                                        </div>
                                        <div class="text-muted-foreground mt-1 flex items-center gap-4 text-sm">
                                            <div class="flex items-center gap-1">
                                                <Calendar class="h-4 w-4" />
                                                {{ formatDate(invoice.created) }}
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <DollarSign class="h-4 w-4" />
                                                {{ formatAmount(invoice.total, invoice.currency) }}
                                            </div>
                                        </div>
                                        <div class="text-muted-foreground mt-1 text-sm">
                                            {{ invoice.customer_name }} ({{ invoice.customer_email }})
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Button
                                        :href="invoice.invoice_pdf"
                                        variant="outline"
                                        size="sm"
                                        class="gap-2"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        as="a"
                                    >
                                        <Download class="h-4 w-4" />
                                        Download PDF
                                    </Button>
                                    <Button
                                        :href="invoice.hosted_invoice_url"
                                        variant="outline"
                                        size="sm"
                                        class="gap-2"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        as="a"
                                    >
                                        <FileText class="h-4 w-4" />
                                        View Online
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div v-else class="py-12 text-center">
                            <Receipt class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                            <h3 class="mb-2 text-lg font-semibold">No invoices yet</h3>
                            <p class="text-muted-foreground mb-6">
                                Your billing history and invoices will appear here once you start a subscription.
                            </p>
                            <Button as-child class="gap-2">
                                <Link :href="route('billing.packages')">
                                    <Receipt class="h-4 w-4" />
                                    View Plans
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Billing Summary -->
                <div class="grid gap-6 md:grid-cols-3">
                    <Card>
                        <CardContent class="p-6">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                                    <Receipt class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-sm">Total Invoices</p>
                                    <p class="text-2xl font-bold">{{ invoices.length }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="p-6">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/20">
                                    <DollarSign class="h-5 w-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-sm">Total Spent</p>
                                    <p class="text-2xl font-bold">
                                        {{
                                            formatAmount(
                                                invoices.reduce((total, invoice) => {
                                                    // Convert total from cents to dollars and add to accumulator
                                                    return total + (invoice.total || 0);
                                                }, 0),
                                                'usd',
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="p-6">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/20">
                                    <Calendar class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <p class="text-muted-foreground text-sm">Since</p>
                                    <p class="text-2xl font-bold">
                                        {{ invoices.length > 0 ? new Date(invoices[0].created * 1000).getFullYear() : new Date().getFullYear() }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Help Section -->
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-start gap-3">
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900/20">
                                <Receipt class="h-4 w-4 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div>
                                <h4 class="mb-1 font-medium">Need help with billing?</h4>
                                <p class="text-muted-foreground mb-3 text-sm">
                                    If you have questions about your invoices or need assistance with billing, our support team is here to help.
                                </p>
                                <Button variant="outline" size="sm"> Contact Support </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
