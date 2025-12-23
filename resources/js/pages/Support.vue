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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Support', href: '/support' }];

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
        const response = await axios.get(route('tickets'));
        tickets.value = response.data as Ticket[];
    } catch (error) {
        // eslint-disable-next-line no-console
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
    if (s === 'open') return { variant: 'secondary' as const, label: 'Open' };
    if (s === 'in_progress') return { variant: 'outline' as const, label: 'In Progress' };
    if (s === 'resolved') return { variant: 'outline' as const, label: 'Resolved' };
    if (s === 'closed') return { variant: 'destructive' as const, label: 'Closed' };
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
    if (p === 'urgent') return { variant: 'destructive' as const, label: 'Urgent' };
    if (p === 'high') return { variant: 'default' as const, label: 'High' };
    if (p === 'medium') return { variant: 'secondary' as const, label: 'Medium' };
    if (p === 'low') return { variant: 'outline' as const, label: 'Low' };
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
        <Head title="Support" />

        <div class="container mx-auto max-w-5xl py-8">
            <div class="space-y-8">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div class="space-y-2">
                        <h1 class="text-3xl font-bold tracking-tight">Support</h1>
                        <p class="text-muted-foreground">View your tickets or create a new one.</p>
                    </div>
                    <Button class="gap-2" @click="isCreateOpen = true">
                        <Plus class="h-4 w-4" />
                        Create New Ticket
                    </Button>
                </div>

                <!-- Tickets List -->
                <div class="flex justify-center">
                    <div class="w-full max-w-5xl">
                        <Card>
                            <CardHeader>
                                <CardTitle>Your Tickets</CardTitle>
                                <CardDescription>Previously submitted tickets</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div v-if="isLoading" class="text-muted-foreground flex items-center justify-center py-12">
                                    <Loader2 class="mr-2 h-5 w-5 animate-spin" /> Loading tickets...
                                </div>

                                <div v-else>
                                    <div v-if="tickets.length === 0" class="text-muted-foreground py-12 text-center">
                                        No tickets yet. Click "Create New Ticket" to get started.
                                    </div>

                                    <div v-else class="overflow-x-auto">
                                        <Table class="text-sm">
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead class="w-16">ID</TableHead>
                                                    <TableHead>Type</TableHead>
                                                    <TableHead>Status</TableHead>
                                                    <TableHead>Priority</TableHead>
                                                    <TableHead>Created</TableHead>
                                                    <TableHead class="hidden lg:table-cell">Message</TableHead>
                                                    <TableHead class="hidden text-right md:table-cell">Replies</TableHead>
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
                                                    <TableCell class="hidden text-right md:table-cell">{{ ticket.replies?.length || 0 }}</TableCell>
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
                            <DialogTitle>Create New Ticket</DialogTitle>
                            <DialogDescription>Choose a type and describe your request.</DialogDescription>
                        </DialogHeader>

                        <form class="space-y-6" @submit.prevent="onSubmit">
                            <div class="grid gap-2">
                                <Label for="support-title">Type</Label>
                                <Select v-model="form.title" required>
                                    <SelectTrigger id="support-title" class="w-full">
                                        <SelectValue placeholder="Select a type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Request a feature">Request a feature</SelectItem>
                                        <SelectItem value="Report a bug">Report a bug</SelectItem>
                                        <SelectItem value="Request integration">Request integration</SelectItem>
                                        <SelectItem value="Request adding model">Request adding model</SelectItem>
                                        <SelectItem value="Refund Request">Refund Request</SelectItem>
                                        <SelectItem value="Billing Issue">Billing Issue</SelectItem>
                                        <SelectItem value="Other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="priority">Priority</Label>
                                <Select v-model="form.priority" required>
                                    <SelectTrigger id="priority" class="w-full">
                                        <SelectValue placeholder="Select priority" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Low</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                        <SelectItem value="urgent">Urgent</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="(form as any).errors?.priority" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="support-message">Message</Label>
                                <Textarea
                                    id="support-message"
                                    v-model="form.message"
                                    rows="8"
                                    placeholder="Describe your request or issue in detail..."
                                    required
                                />
                                <InputError :message="form.errors.message" />
                            </div>

                            <DialogFooter class="gap-2">
                                <Button type="button" variant="outline" @click="isCreateOpen = false">Cancel</Button>
                                <Button type="submit" :disabled="form.processing || !form.title || !form.message">
                                    <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                                    {{ form.processing ? 'Submitting...' : 'Submit' }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </AppLayout>
</template>
