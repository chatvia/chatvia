<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import { getInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import WorkspaceLayout from '@/layouts/workspace/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ScrollText, ShieldCheck, Sparkles, User } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface Row {
    id: number;
    created_at: string;
    action: string;
    action_label: string;
    category: string | null;
    outcome: string;
    user_name: string | null;
    user_email: string | null;
    avatar: string | null;
    target_label: string | null;
    target_type: string | null;
    target_id: string | null;
    ip: string | null;
    user_agent: string | null;
    metadata: Record<string, unknown> | null;
    integrity_verified: boolean;
}

interface Props {
    entitled: boolean;
    rows: { data: Row[]; next_page_url: string | null; prev_page_url: string | null };
    categories: { value: string; label: string }[];
    outcomes: string[];
    filters: { category: string | null; outcome: string | null };
}

const props = defineProps<Props>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

const breadcrumbs: BreadcrumbItem[] = [{ title: t('workspace.security_audit.breadcrumb'), href: '/my/workspace/security/audit' }];

const category = ref(props.filters.category ?? 'all');
const outcome = ref(props.filters.outcome ?? 'all');

const applyFilters = () => {
    router.get(
        route('security.audit'),
        {
            category: category.value === 'all' ? undefined : category.value,
            outcome: outcome.value === 'all' ? undefined : outcome.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const go = (url: string | null) => {
    if (url) router.get(url, {}, { preserveScroll: true, preserveState: true });
};

const outcomeVariant = (o: string): 'secondary' | 'destructive' => (o === 'failure' ? 'destructive' : 'secondary');

const selected = ref<Row | null>(null);
const detailOpen = ref(false);
const openDetail = (row: Row) => {
    selected.value = row;
    detailOpen.value = true;
};

const prettyMetadata = (metadata: unknown): string => {
    if (!metadata || (typeof metadata === 'object' && Object.keys(metadata).length === 0)) return '—';
    try {
        return JSON.stringify(metadata, null, 2);
    } catch {
        return String(metadata);
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('workspace.security_audit.title')" />

        <WorkspaceLayout width="wide">
            <div class="w-full space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <ScrollText class="size-6" />
                        {{ t('workspace.security_audit.title') }}
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">{{ t('workspace.security_audit.description') }}</p>
                </div>

                <!-- Upsell teaser — non-entitled workspaces -->
                <Card v-if="!entitled">
                    <CardContent class="flex flex-col items-center gap-4 py-12 text-center">
                        <div class="bg-primary/10 flex size-12 items-center justify-center rounded-full">
                            <Sparkles class="text-primary size-6" />
                        </div>
                        <div class="space-y-1">
                            <h2 class="text-lg font-semibold">{{ t('workspace.security_audit.teaser.title') }}</h2>
                            <p class="text-muted-foreground mx-auto max-w-md text-sm">{{ t('workspace.security_audit.teaser.description') }}</p>
                        </div>
                        <Button as-child>
                            <Link :href="route('billing.packages')">{{ t('workspace.security_audit.teaser.cta') }}</Link>
                        </Button>
                    </CardContent>
                </Card>

                <template v-else>
                    <!-- Filters -->
                    <div class="flex flex-wrap gap-3">
                        <Select v-model="category" @update:model-value="applyFilters">
                            <SelectTrigger class="w-52">
                                <SelectValue :placeholder="t('workspace.security_audit.all_categories')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{ t('workspace.security_audit.all_categories') }}</SelectItem>
                                <SelectItem v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-model="outcome" @update:model-value="applyFilters">
                            <SelectTrigger class="w-44">
                                <SelectValue :placeholder="t('workspace.security_audit.all_outcomes')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{ t('workspace.security_audit.all_outcomes') }}</SelectItem>
                                <SelectItem v-for="o in outcomes" :key="o" :value="o" class="capitalize">{{ o }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="bg-card rounded-lg border">
                        <Table class="[&_td]:px-4 [&_td]:py-3 [&_th]:px-4">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{{ t('workspace.security_audit.table.actor') }}</TableHead>
                                    <TableHead>{{ t('workspace.security_audit.table.action') }}</TableHead>
                                    <TableHead>{{ t('workspace.security_audit.table.outcome') }}</TableHead>
                                    <TableHead>{{ t('workspace.security_audit.table.ip') }}</TableHead>
                                    <TableHead class="text-end">{{ t('workspace.security_audit.table.when') }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in rows.data" :key="row.id" class="hover:bg-muted/50 cursor-pointer" @click="openDetail(row)">
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Avatar class="size-7">
                                                <AvatarImage v-if="row.user_name && row.avatar" :src="row.avatar" :alt="row.user_name" />
                                                <AvatarFallback class="text-xs">
                                                    <template v-if="row.user_name">{{ getInitials(row.user_name) }}</template>
                                                    <User v-else class="text-muted-foreground size-3.5" />
                                                </AvatarFallback>
                                            </Avatar>
                                            <span :class="row.user_name ? 'text-sm' : 'text-muted-foreground text-sm'">{{
                                                row.user_name ?? t('workspace.security_audit.system')
                                            }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-sm">{{ row.action_label }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="outcomeVariant(row.outcome)" class="capitalize">{{ row.outcome }}</Badge>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground font-mono text-xs">{{ row.ip ?? '—' }}</TableCell>
                                    <TableCell class="text-muted-foreground text-end whitespace-nowrap">{{
                                        formatDateTime(row.created_at)
                                    }}</TableCell>
                                </TableRow>
                                <TableRow v-if="rows.data.length === 0">
                                    <TableCell colspan="5" class="text-muted-foreground py-10 text-center text-sm">
                                        {{ t('workspace.security_audit.empty') }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <div v-if="rows.prev_page_url || rows.next_page_url" class="flex justify-end gap-2">
                        <Button variant="outline" size="sm" :disabled="!rows.prev_page_url" @click="go(rows.prev_page_url)">
                            {{ t('workspace.security_audit.previous') }}
                        </Button>
                        <Button variant="outline" size="sm" :disabled="!rows.next_page_url" @click="go(rows.next_page_url)">
                            {{ t('workspace.security_audit.next') }}
                        </Button>
                    </div>
                </template>
            </div>
        </WorkspaceLayout>

        <!-- Detail -->
        <Dialog v-model:open="detailOpen">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        {{ selected?.action_label }}
                        <Badge v-if="selected" :variant="outcomeVariant(selected.outcome)" class="capitalize">{{ selected.outcome }}</Badge>
                    </DialogTitle>
                    <DialogDescription>
                        {{ selected ? formatDateTime(selected.created_at) : '' }} ·
                        {{ selected?.user_name ?? selected?.user_email ?? t('workspace.security_audit.system') }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selected" class="space-y-4 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">{{ t('workspace.security_audit.table.ip') }}</p>
                            <p class="font-mono text-xs">{{ selected.ip ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">{{ t('workspace.security_audit.target') }}</p>
                            <p>{{ selected.target_label ?? selected.target_type ?? '—' }}</p>
                        </div>
                    </div>
                    <div v-if="selected.user_agent">
                        <p class="text-muted-foreground mb-1 text-xs uppercase">{{ t('workspace.security_audit.user_agent') }}</p>
                        <p class="text-muted-foreground text-xs break-words">{{ selected.user_agent }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground mb-1 text-xs uppercase">{{ t('workspace.security_audit.metadata') }}</p>
                        <pre class="bg-muted/40 max-h-60 overflow-y-auto rounded-md p-3 text-xs break-words whitespace-pre-wrap">{{
                            prettyMetadata(selected.metadata)
                        }}</pre>
                    </div>
                    <div v-if="selected.integrity_verified" class="text-muted-foreground flex items-center gap-1.5 text-xs">
                        <ShieldCheck class="size-3.5" /> {{ t('workspace.security_audit.integrity') }}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
