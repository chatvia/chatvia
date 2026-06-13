<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { onMounted, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Table from '@/components/ui/table/Table.vue';
import TableBody from '@/components/ui/table/TableBody.vue';
import TableCell from '@/components/ui/table/TableCell.vue';
import TableHead from '@/components/ui/table/TableHead.vue';
import TableHeader from '@/components/ui/table/TableHeader.vue';
import TableRow from '@/components/ui/table/TableRow.vue';
import { Textarea } from '@/components/ui/textarea';
import { Loader2, Plus } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('support.title'), href: '/support' }];

type Ticket = {
    id: number;
    user_id: number;
    assigned_to: number | null;
    title: string;
    message: string;
    status: string;
    priority: 'low' | 'medium' | 'high' | string;
    resolved_at: string | null;
    created_at: string;
    updated_at: string;
    replies: Array<any>;
};

const tickets = ref<Ticket[]>([]);
const isLoading = ref<boolean>(true);
const isCreateOpen = ref<boolean>(false);

const form = useForm<{ title: string; message: string; priority: 'low' | 'medium' | 'high' | 'urgent' }>({
    title: 'Request a feature',
    message: '',
    priority: 'low',
});

const fetchTickets = async () => {
    try {
        const response = await axios.get(route('tickets.index'));
        tickets.value = response.data as Ticket[];
    } catch (error) {
        console.error('Failed to fetch tickets', error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchTickets();
});

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const statusBadge = (status: string) => {
    const s = status.toLowerCase();
    if (s === 'open') return { variant: 'secondary' as const, label: t('support.status_open') };
    if (s === 'in_progress') return { variant: 'outline' as const, label: t('support.status_in_progress') };
    if (s === 'resolved') return { variant: 'outline' as const, label: t('support.status_resolved') };
    if (s === 'closed') return { variant: 'destructive' as const, label: t('support.status_closed') };
    return { variant: 'outline' as const, label: status };
};

const statusBadgeClass = (status: string) => {
    const s = status.toLowerCase();
    if (s === 'resolved') {
        return 'text-green-600 border-green-200 bg-green-50 dark:bg-green-950/30 dark:border-green-800';
    }
    return '';
};

const priorityBadge = (priority: string) => {
    const p = priority.toLowerCase();
    if (p === 'urgent') return { variant: 'destructive' as const, label: t('support.priority_urgent') };
    if (p === 'high') return { variant: 'default' as const, label: t('support.priority_high') };
    if (p === 'medium') return { variant: 'secondary' as const, label: t('support.priority_medium') };
    if (p === 'low') return { variant: 'outline' as const, label: t('support.priority_low') };
    return { variant: 'outline' as const, label: priority };
};

const excerpt = (text: string, len = 60) => {
    if (!text) return '';
    return text.length > len ? text.substring(0, len) + '…' : text;
};

const onSubmit = () => {
    form.post(route('tickets.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isCreateOpen.value = false;
            form.reset();
            form.title = 'Request a feature';
            form.priority = 'low';
            fetchTickets();
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('support.title')" />

        <div class="container mx-auto max-w-5xl py-8">
            <div class="space-y-8">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div class="space-y-2">
                        <h1 class="text-3xl font-bold tracking-tight">{{ t('support.title') }}</h1>
                        <p class="text-muted-foreground">{{ t('support.subtitle') }}</p>
                    </div>
                    <Button class="gap-2" @click="isCreateOpen = true">
                        <Plus class="h-4 w-4" />
                        {{ t('support.create_ticket') }}
                    </Button>
                </div>

                <!-- Tickets List -->
                <div class="flex justify-center">
                    <div class="w-full max-w-5xl">
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ t('support.your_tickets') }}</CardTitle>
                                <CardDescription>{{ t('support.your_tickets_description') }}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="isLoading" class="text-muted-foreground flex items-center justify-center py-12">
                                    <Loader2 class="me-2 h-5 w-5 animate-spin" /> {{ t('support.loading') }}
                                </div>

                                <div v-else>
                                    <div v-if="tickets.length === 0" class="text-muted-foreground py-12 text-center">
                                        {{ t('support.empty') }}
                                    </div>

                                    <div v-else class="overflow-x-auto">
                                        <Table class="text-sm">
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead class="w-16">{{ t('support.th_id') }}</TableHead>
                                                    <TableHead>{{ t('support.th_type') }}</TableHead>
                                                    <TableHead>{{ t('support.th_status') }}</TableHead>
                                                    <TableHead>{{ t('support.th_priority') }}</TableHead>
                                                    <TableHead>{{ t('support.th_created') }}</TableHead>
                                                    <TableHead class="hidden lg:table-cell">{{ t('support.th_message') }}</TableHead>
                                                    <TableHead class="hidden text-end md:table-cell">{{ t('support.th_replies') }}</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                <TableRow
                                                    v-for="ticket in tickets"
                                                    :key="ticket.id"
                                                    class="hover:bg-muted/50 cursor-pointer"
                                                    @click="router.visit(route('tickets.show', { ticket: ticket.id }))"
                                                >
                                                    <TableCell class="font-medium">#{{ ticket.id }}</TableCell>
                                                    <TableCell class="capitalize">{{ ticket.title }}</TableCell>
                                                    <TableCell>
                                                        <Badge :variant="statusBadge(ticket.status).variant" :class="statusBadgeClass(ticket.status)">
                                                            {{ statusBadge(ticket.status).label }}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge :variant="priorityBadge(ticket.priority).variant">{{
                                                            priorityBadge(ticket.priority).label
                                                        }}</Badge>
                                                    </TableCell>
                                                    <TableCell>{{ formatDate(ticket.created_at) }}</TableCell>
                                                    <TableCell class="text-muted-foreground hidden max-w-[320px] truncate lg:table-cell">{{
                                                        excerpt(ticket.message)
                                                    }}</TableCell>
                                                    <TableCell class="hidden text-end md:table-cell">{{ ticket.replies?.length || 0 }}</TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Create Ticket Modal -->
                <Dialog v-model:open="isCreateOpen">
                    <DialogContent class="sm:max-w-[600px]">
                        <DialogHeader>
                            <DialogTitle>{{ t('support.create_ticket') }}</DialogTitle>
                            <DialogDescription>{{ t('support.create_ticket_description') }}</DialogDescription>
                        </DialogHeader>

                        <form class="space-y-6" @submit.prevent="onSubmit">
                            <div class="grid gap-2">
                                <Label for="support-title">{{ t('support.label_type') }}</Label>
                                <Select v-model="form.title" required>
                                    <SelectTrigger id="support-title" class="w-full">
                                        <SelectValue :placeholder="t('support.type_placeholder')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Request a feature">{{ t('support.type_feature') }}</SelectItem>
                                        <SelectItem value="Report a bug">{{ t('support.type_bug') }}</SelectItem>
                                        <SelectItem value="Request integration">{{ t('support.type_integration') }}</SelectItem>
                                        <SelectItem value="Request adding model">{{ t('support.type_add_model') }}</SelectItem>
                                        <SelectItem value="Refund Request">{{ t('support.type_refund') }}</SelectItem>
                                        <SelectItem value="Billing Issue">{{ t('support.type_billing') }}</SelectItem>
                                        <SelectItem value="Other">{{ t('support.type_other') }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="priority">{{ t('support.label_priority') }}</Label>
                                <Select v-model="form.priority" required>
                                    <SelectTrigger id="priority" class="w-full">
                                        <SelectValue :placeholder="t('support.priority_placeholder')" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">{{ t('support.priority_low') }}</SelectItem>
                                        <SelectItem value="medium">{{ t('support.priority_medium') }}</SelectItem>
                                        <SelectItem value="high">{{ t('support.priority_high') }}</SelectItem>
                                        <SelectItem value="urgent">{{ t('support.priority_urgent') }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="(form as any).errors?.priority" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="support-message">{{ t('support.label_message') }}</Label>
                                <Textarea
                                    id="support-message"
                                    v-model="form.message"
                                    rows="8"
                                    :placeholder="t('support.message_placeholder')"
                                    required
                                />
                                <InputError :message="form.errors.message" />
                            </div>

                            <DialogFooter class="gap-2">
                                <Button type="button" variant="outline" @click="isCreateOpen = false">{{ t('support.cancel') }}</Button>
                                <Button type="submit" :disabled="form.processing || !form.title || !form.message">
                                    <Loader2 v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                                    {{ form.processing ? t('support.submitting') : t('support.submit') }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </AppLayout>
</template>
