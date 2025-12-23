<script setup lang="ts">
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

type SupportType = 'feature' | 'bug' | 'file_type' | 'other' | string;

type TicketReply = {
    id: number;
    user_id: number | null;
    message: string;
    created_at: string;
    type: 'user' | 'staff';
};

type Ticket = {
    id: number;
    user_id: number;
    assigned_to: number | null;
    title: SupportType;
    message: string;
    status: string;
    priority: 'low' | 'medium' | 'high' | string;
    resolved_at: string | null;
    created_at: string;
    updated_at: string;
    replies: TicketReply[];
};

const props = defineProps<{ ticket: Ticket }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Support', href: route('tickets') },
    { title: `Ticket #${props.ticket.id}`, href: route('tickets.show', { ticket: props.ticket.id }) },
];

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusBadge = (status: string) => {
    const s = (status || '').toLowerCase();
    if (s === 'open') return { variant: 'secondary' as const, label: 'Open' };
    if (s === 'in_progress') return { variant: 'outline' as const, label: 'In Progress' };
    if (s === 'resolved') return { variant: 'default' as const, label: 'Resolved' };
    if (s === 'closed') return { variant: 'destructive' as const, label: 'Closed' };
    return { variant: 'outline' as const, label: status };
};

const priorityBadge = (priority: string) => {
    const p = (priority || '').toLowerCase();
    if (p === 'urgent') return { variant: 'destructive' as const, label: 'Urgent' };
    if (p === 'high') return { variant: 'default' as const, label: 'High' };
    if (p === 'medium') return { variant: 'secondary' as const, label: 'Medium' };
    if (p === 'low') return { variant: 'outline' as const, label: 'Low' };
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
const priorityForm = useForm<{ priority: 'low' | 'medium' | 'high' | 'urgent' }>({
    priority: (props.ticket.priority as any) || 'medium',
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
        <Head :title="`Ticket #${ticket.id}`" />

        <div class="container mx-auto max-w-4xl py-8">
            <div class="mb-6 flex items-center justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-bold tracking-tight">Ticket #{{ ticket.id }}</h1>
                    <p class="text-muted-foreground text-sm">Created {{ formatDateTime(ticket.created_at) }}</p>
                </div>
                <Button as-child variant="outline">
                    <Link :href="route('support')" class="gap-2"> <ArrowLeft class="h-4 w-4" /> Back to Support </Link>
                </Button>
            </div>

            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="capitalize">{{ ticket.title }}</CardTitle>
                    <CardDescription class="flex flex-wrap items-center gap-2">
                        <Badge :variant="statusBadge(ticket.status).variant">{{ statusBadge(ticket.status).label }}</Badge>
                        <Badge :variant="priorityBadge(ticket.priority).variant">{{ priorityBadge(ticket.priority).label }}</Badge>
                        <Button v-if="ticket.status !== 'resolved'" size="sm" variant="outline" class="ml-2" @click="showPriorityDialog = true">
                            Change Priority
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
                        <DialogTitle>Change Ticket Priority</DialogTitle>
                        <DialogDescription>
                            Please change the priority only if this is truly urgent. Misusing the urgent priority can slow down responses for
                            everyone.
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitPriority">
                        <div class="grid gap-2">
                            <Label for="priority-select">Priority</Label>
                            <Select v-model="priorityForm.priority" required>
                                <SelectTrigger id="priority-select" class="w-full">
                                    <SelectValue placeholder="Select a priority" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Low</SelectItem>
                                    <SelectItem value="medium">Medium</SelectItem>
                                    <SelectItem value="high">High</SelectItem>
                                    <SelectItem value="urgent">Urgent</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="priorityForm.errors.priority as any" />
                        </div>
                        <DialogFooter class="gap-2">
                            <Button type="button" variant="outline" @click="showPriorityDialog = false">Cancel</Button>
                            <Button type="submit" :disabled="priorityForm.processing">
                                <Loader2 v-if="priorityForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                                {{ priorityForm.processing ? 'Saving...' : 'Save' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" /> Replies ({{ ticket.replies?.length || 0 }})
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="!ticket.replies || ticket.replies.length === 0" class="text-muted-foreground py-8 text-center">No replies yet.</div>
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
                                        {{ reply.type === 'staff' ? 'Staff' : 'User' }}
                                    </Badge>
                                </div>
                                <div class="text-muted-foreground">{{ formatDateTime(reply.created_at) }}</div>
                            </div>
                            <div class="text-sm whitespace-pre-line">{{ reply.message }}</div>
                        </div>
                    </div>

                    <!-- Reply form / Resolved notice -->
                    <div v-if="ticket.status === 'resolved'" class="bg-muted/50 text-muted-foreground mt-6 rounded-md border p-4 text-sm">
                        This ticket has been resolved. If the issue persists, please open a new ticket from the Support page.
                    </div>
                    <form v-else class="mt-6 space-y-3" @submit.prevent="submitReply">
                        <div class="grid gap-2">
                            <Label for="reply-message">Add a reply</Label>
                            <Textarea id="reply-message" v-model="replyForm.message" rows="5" placeholder="Write your reply..." required />
                            <InputError :message="replyForm.errors.message" />
                        </div>
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="replyForm.processing || !replyForm.message">
                                <Loader2 v-if="replyForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                                {{ replyForm.processing ? 'Sending...' : 'Send reply' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
