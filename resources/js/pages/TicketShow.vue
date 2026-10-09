<script setup lang="ts">
/**
 * One support ticket: the original message, the reply thread, a reply form and
 * a dialog to change the priority.
 */
import type { Ticket } from '@/api/tickets';
import { useDateFormat } from '@/composables/useDateFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ArrowLeft, Loader2, MessageSquare } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

const props = defineProps<{ ticket: Ticket }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('ticket.support'), href: route('tickets.index') },
    { title: t('ticket.title', { id: props.ticket.id }), href: route('tickets.show', { ticket: props.ticket.id }) },
];

const statusBadge = (status: string) => {
    const s = (status || '').toLowerCase();
    if (s === 'open') return { variant: 'secondary' as const, label: t('ticket.status_open') };
    if (s === 'in_progress') return { variant: 'outline' as const, label: t('ticket.status_in_progress') };
    if (s === 'resolved') return { variant: 'default' as const, label: t('ticket.status_resolved') };
    if (s === 'closed') return { variant: 'destructive' as const, label: t('ticket.status_closed') };
    return { variant: 'outline' as const, label: status };
};

const priorityBadge = (priority: string) => {
    const p = (priority || '').toLowerCase();
    if (p === 'urgent') return { variant: 'destructive' as const, label: t('ticket.priority_urgent') };
    if (p === 'high') return { variant: 'default' as const, label: t('ticket.priority_high') };
    if (p === 'medium') return { variant: 'secondary' as const, label: t('ticket.priority_medium') };
    if (p === 'low') return { variant: 'outline' as const, label: t('ticket.priority_low') };
    return { variant: 'outline' as const, label: priority };
};

const replyForm = useForm({
    message: '',
});

const submitReply = () => {
    replyForm.post(route('tickets.reply.store', { ticket: props.ticket.id }), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
            router.reload({ only: ['ticket'] });
        },
    });
};

// Change Priority dialog
const showPriorityDialog = ref(false);
type TicketPriority = 'low' | 'medium' | 'high' | 'urgent';

const priorityForm = useForm<{ priority: TicketPriority }>({
    priority: (props.ticket.priority as TicketPriority) || 'medium',
});

const submitPriority = () => {
    priorityForm.post(route('tickets.priority', { ticket: props.ticket.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showPriorityDialog.value = false;
            router.reload({ only: ['ticket'] });
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('ticket.title', { id: ticket.id })" />

        <div class="container mx-auto max-w-4xl py-8">
            <div class="mb-6 flex items-center justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('ticket.title', { id: ticket.id }) }}</h1>
                    <p class="text-muted-foreground text-sm">{{ t('ticket.created_at', { date: formatDateTime(ticket.created_at) }) }}</p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="route('support')" class="gap-2"> <ArrowLeft class="h-4 w-4" /> {{ t('ticket.back_to_support') }} </Link>
                </Button>
            </div>

            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="capitalize">{{ ticket.title }}</CardTitle>
                    <CardDescription class="flex flex-wrap items-center gap-2">
                        <Badge :variant="statusBadge(ticket.status).variant">{{ statusBadge(ticket.status).label }}</Badge>
                        <Badge :variant="priorityBadge(ticket.priority).variant">{{ priorityBadge(ticket.priority).label }}</Badge>
                        <Button v-if="ticket.status !== 'resolved'" size="sm" variant="outline" class="ms-2" @click="showPriorityDialog = true">
                            {{ t('ticket.change_priority') }}
                        </Button>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="leading-relaxed whitespace-pre-line">{{ ticket.message }}</p>
                </CardContent>
            </Card>

            <!-- Change Priority Modal -->
            <Dialog v-if="ticket.status !== 'resolved'" v-model:open="showPriorityDialog">
                <DialogContent class="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>{{ t('ticket.change_priority_title') }}</DialogTitle>
                        <DialogDescription>
                            {{ t('ticket.change_priority_description') }}
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitPriority">
                        <div class="grid gap-2">
                            <Label for="priority-select">{{ t('ticket.label_priority') }}</Label>
                            <Select v-model="priorityForm.priority" required>
                                <SelectTrigger id="priority-select" class="w-full">
                                    <SelectValue :placeholder="t('ticket.priority_placeholder')" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">{{ t('ticket.priority_low') }}</SelectItem>
                                    <SelectItem value="medium">{{ t('ticket.priority_medium') }}</SelectItem>
                                    <SelectItem value="high">{{ t('ticket.priority_high') }}</SelectItem>
                                    <SelectItem value="urgent">{{ t('ticket.priority_urgent') }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="priorityForm.errors.priority as any" />
                        </div>
                        <DialogFooter class="gap-2">
                            <Button type="button" variant="outline" @click="showPriorityDialog = false">{{ t('ticket.cancel') }}</Button>
                            <Button type="submit" :disabled="priorityForm.processing">
                                <Loader2 v-if="priorityForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ priorityForm.processing ? t('ticket.saving') : t('ticket.save') }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" /> {{ t('ticket.replies', { count: ticket.replies?.length || 0 }) }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="!ticket.replies || ticket.replies.length === 0" class="text-muted-foreground py-8 text-center">
                        {{ t('ticket.no_replies') }}
                    </div>
                    <div v-else class="space-y-4">
                        <div
                            v-for="reply in ticket.replies"
                            :key="reply.id"
                            :class="[
                                'rounded-lg border p-4',
                                reply.type === 'staff' ? 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/20' : 'bg-muted/40',
                            ]"
                        >
                            <div class="mb-2 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <Badge :variant="reply.type === 'staff' ? 'default' : 'secondary'">
                                        {{ reply.type === 'staff' ? t('ticket.role_staff') : t('ticket.role_user') }}
                                    </Badge>
                                </div>
                                <div class="text-muted-foreground">{{ formatDateTime(reply.created_at) }}</div>
                            </div>
                            <div class="text-sm whitespace-pre-line">{{ reply.message }}</div>
                        </div>
                    </div>

                    <!-- Reply form / Resolved notice -->
                    <div v-if="ticket.status === 'resolved'" class="bg-muted/50 text-muted-foreground mt-6 rounded-md border p-4 text-sm">
                        {{ t('ticket.resolved_notice') }}
                    </div>
                    <form v-else class="mt-6 space-y-3" @submit.prevent="submitReply">
                        <div class="grid gap-2">
                            <Label for="reply-message">{{ t('ticket.add_reply') }}</Label>
                            <Textarea id="reply-message" v-model="replyForm.message" rows="5" :placeholder="t('ticket.reply_placeholder')" required />
                            <InputError :message="replyForm.errors.message" />
                        </div>
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="replyForm.processing || !replyForm.message">
                                <Loader2 v-if="replyForm.processing" class="me-2 h-4 w-4 animate-spin" />
                                {{ replyForm.processing ? t('ticket.sending') : t('ticket.send_reply') }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
