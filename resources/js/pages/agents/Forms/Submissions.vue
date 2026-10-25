<script lang="ts" setup>
import DeleteConfirmationDialog from '@/components/common/DeleteConfirmationDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { index as formsIndex } from '@/routes/agents/forms';
import { destroy as destroySubmissions, exportMethod as exportSubmissions } from '@/routes/agents/forms/submissions';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Download, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Field {
    key: string;
    label: string;
}
interface FormDef {
    external_id: string;
    name: string;
    schema: Field[];
}
interface Submission {
    external_id: string;
    values: Record<string, unknown>;
    submitted_at: string | null;
    created_at: string;
}

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    form: FormDef;
    submissions: Submission[];
}>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();
const { hasPermission } = useTeamPermissions();

// Deleting leads is part of managing lead capture; exporting has its own permission.
const canDelete = computed(() => hasPermission('agent.leads.settings'));
const canExport = computed(() => hasPermission('agent.leads.export'));

const search = ref('');
// Cap the visible columns so wide forms stay readable; the CSV has them all.
const columns = computed(() => props.form.schema.slice(0, 5));

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.submissions;
    return props.submissions.filter((s) => Object.values(s.values).join(' ').toLowerCase().includes(q));
});

function cell(s: Submission, key: string): string {
    const v = s.values[key];
    return v === null || v === undefined ? '' : String(v);
}

function when(s: Submission): string {
    return formatDateTime(s.submitted_at ?? s.created_at);
}

function exportCsv(): void {
    window.location.href = exportSubmissions.url({ agent: props.agent.external_id, form: props.form.external_id });
}

// The row waiting for the delete confirmation, if any.
const pendingDelete = ref<Submission | null>(null);

function confirmDelete(): void {
    if (pendingDelete.value) remove(pendingDelete.value);
    pendingDelete.value = null;
}

function remove(s: Submission): void {
    router.delete(destroySubmissions.url({ agent: props.agent.external_id, form: props.form.external_id }), {
        data: { external_ids: [s.external_id] },
        preserveScroll: true,
        onSuccess: () => toast.success(t('agents.submissions.deleted')),
    });
}
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.submissions.page_title')">
        <div class="mx-auto w-full max-w-6xl space-y-4">
            <Link
                :href="formsIndex.url(agent.external_id)"
                class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
            >
                <ArrowLeft class="h-4 w-4" /> {{ t('agents.submissions.back_to_forms') }}
            </Link>

            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <CardTitle>{{ form.name }}</CardTitle>
                            <CardDescription>{{ t('agents.submissions.count', { n: submissions.length }) }}</CardDescription>
                        </div>
                        <Button v-if="canExport" variant="outline" :disabled="submissions.length === 0" @click="exportCsv">
                            <Download class="me-1 h-4 w-4" /> {{ t('agents.submissions.export') }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="relative max-w-sm">
                        <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input v-model="search" :placeholder="t('agents.submissions.search')" class="ps-9" />
                    </div>

                    <div v-if="filtered.length === 0" class="text-muted-foreground rounded-md border py-12 text-center text-sm">
                        {{ t('agents.submissions.empty') }}
                    </div>
                    <div v-else class="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead v-for="col in columns" :key="col.key" class="h-11 ps-5">{{ col.label }}</TableHead>
                                    <TableHead class="h-11">{{ t('agents.submissions.submitted_at') }}</TableHead>
                                    <TableHead v-if="canDelete" class="h-11 w-12 pe-5" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="s in filtered" :key="s.external_id">
                                    <TableCell v-for="col in columns" :key="col.key" class="ps-5">{{ cell(s, col.key) }}</TableCell>
                                    <TableCell class="text-muted-foreground text-xs">{{ when(s) }}</TableCell>
                                    <TableCell v-if="canDelete" class="pe-5">
                                        <Button variant="ghost" size="icon" :aria-label="t('common.delete')" @click="pendingDelete = s"
                                            ><Trash2 class="h-4 w-4"
                                        /></Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <DeleteConfirmationDialog :open="pendingDelete !== null" @confirm="confirmDelete" @cancel="pendingDelete = null" />
    </AgentLayout>
</template>
