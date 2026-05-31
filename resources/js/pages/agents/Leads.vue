<script lang="ts" setup>
import FormErrors from '@/components/FormErrors.vue';
import LeadDetailsSheet from '@/components/LeadDetailsSheet.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle, Download, ExternalLink, Eye, LoaderCircle, Plug, Search, Settings, Trash2, Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
    settings?: {
        fields?: any;
    } | null;
    leadsConnectorAttached: boolean;
    connectorsUrl: string;
}

const props = defineProps<Props>();

// Permission checks
const { hasPermission } = useTeamPermissions();
const canManageLeadSettings = computed(() => hasPermission('agent.leads.settings'));
const canExportLeads = computed(() => hasPermission('agent.leads.export'));

// Leads data loaded via Axios
const leads = ref<Domain.Agents.Data.Resources.LeadResourceData[]>([]);
const isLoadingLeads = ref(true);

const fetchLeads = async () => {
    isLoadingLeads.value = true;
    try {
        const response = await axios.get(route('agents.leads.index', { agent: props.agent.external_id }));
        leads.value = response.data.data ?? [];
    } catch (error) {
        console.error('Failed to fetch leads:', error);
        toast.error('Failed to load leads');
    } finally {
        isLoadingLeads.value = false;
    }
};

onMounted(() => {
    fetchLeads();
});

// Get dynamic lead fields from settings or use static defaults
const getLeadFieldsFromSettings = (): Record<LeadFieldKey, LeadFieldConfig> => {
    const leadsSettings = props.settings?.fields; // This is settings('leads')
    const settingsFields = leadsSettings?.fields; // The fields within the leads settings

    // If we have settings fields, use them
    if (settingsFields && typeof settingsFields === 'object') {
        const result: Record<LeadFieldKey, LeadFieldConfig> = {} as any;

        leadFieldOrder.forEach((key) => {
            const settingValue = settingsFields[key];
            if (settingValue && typeof settingValue === 'object') {
                const defaultField = createDefaultLeadField(key);
                result[key] = {
                    title: settingValue.title || defaultField.title,
                    required: Boolean(settingValue.required),
                };
            } else {
                result[key] = createDefaultLeadField(key);
            }
        });

        return result;
    }

    // Fallback to static defaults
    const result: Record<LeadFieldKey, LeadFieldConfig> = {} as any;
    leadFieldOrder.forEach((key) => {
        result[key] = createDefaultLeadField(key);
    });
    return result;
};

// State
const searchQuery = ref('');
const selectedLeads = ref<number[]>([]);
const showDeleteDialog = ref(false);
const isDeleting = ref(false);
const isExporting = ref(false);
const showLeadDetails = ref(false);
const selectedLeadForView = ref<Domain.Agents.Data.Resources.LeadResourceData | null>(null);

// Settings dialog state
const showSettingsDialog = ref(false);
type LeadFieldKey = 'name' | 'email' | 'phone' | 'job_title' | 'company' | 'location' | 'message';
type LeadFieldConfig = { title: string; required: boolean };
const leadFieldOrder: LeadFieldKey[] = ['name', 'email', 'phone', 'job_title', 'company', 'location', 'message'];

// Create default field configuration when no settings exist
const createDefaultLeadField = (key: LeadFieldKey): LeadFieldConfig => {
    const defaults: Record<LeadFieldKey, LeadFieldConfig> = {
        name: { title: 'Name', required: true },
        email: { title: 'Email', required: true },
        phone: { title: 'Phone', required: false },
        job_title: { title: 'Job Title', required: false },
        company: { title: 'Company', required: false },
        location: { title: 'Location', required: false },
        message: { title: 'Message', required: false },
    };
    return defaults[key];
};

// Inertia form for settings
const settingsForm = useForm({
    fields: getLeadFieldsFromSettings(),
});

// Computed properties
const filteredLeads = computed(() => {
    if (!searchQuery.value.trim()) return leads.value;

    const query = searchQuery.value.toLowerCase();
    return leads.value.filter((lead) => lead.name?.toLowerCase().includes(query) || lead.email?.toLowerCase().includes(query));
});

const isAllSelected = computed(() => {
    return filteredLeads.value.length > 0 && filteredLeads.value.every((lead) => selectedLeads.value.includes(lead.id));
});

const isIndeterminate = computed(() => {
    const selectedCount = filteredLeads.value.filter((lead) => selectedLeads.value.includes(lead.id)).length;
    return selectedCount > 0 && selectedCount < filteredLeads.value.length;
});

const hasSelectedLeads = computed(() => selectedLeads.value.length > 0);

// Methods
const toggleSelectAll = (checked: boolean | 'indeterminate') => {
    const isChecked = checked === true;

    if (isChecked) {
        // Select all visible leads
        const visibleIds = filteredLeads.value.map((lead) => lead.id);
        const newSelection = [...new Set([...selectedLeads.value, ...visibleIds])];
        selectedLeads.value = newSelection;
    } else {
        // Deselect all visible leads
        selectedLeads.value = selectedLeads.value.filter((id) => !filteredLeads.value.some((lead) => lead.id === id));
    }
};

const toggleSelectLead = (leadId: number, checked: boolean | 'indeterminate') => {
    const isChecked = checked === true;

    if (isChecked) {
        if (!selectedLeads.value.includes(leadId)) {
            selectedLeads.value.push(leadId);
        }
    } else {
        selectedLeads.value = selectedLeads.value.filter((id) => id !== leadId);
    }
};

const clearSelection = () => {
    selectedLeads.value = [];
};

const viewLead = (lead: Domain.Agents.Data.Resources.LeadResourceData) => {
    selectedLeadForView.value = lead;
    showLeadDetails.value = true;
};

const saveSettings = () => {
    settingsForm.patch(route('agents.leads.settings', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            showSettingsDialog.value = false;
            toast.success('Lead settings saved');
            settingsForm.clearErrors();
        },
        onError: (errors) => {
            console.error('Form errors:', errors);
        },
    });
};

const openSettings = () => {
    if (!canManageLeadSettings.value) {
        toast.error('Permission denied', {
            description: 'You do not have permission to manage lead settings.',
        });
        return;
    }

    settingsForm.clearErrors();

    settingsForm.fields = getLeadFieldsFromSettings();

    showSettingsDialog.value = true;
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const openDeleteDialog = () => {
    if (!hasSelectedLeads.value) return;
    showDeleteDialog.value = true;
};

const deleteSelectedLeads = async () => {
    if (!hasSelectedLeads.value) return;

    isDeleting.value = true;

    try {
        await axios.delete(route('agents.leads.destroy', { agent: props.agent.external_id }), {
            data: {
                lead_ids: selectedLeads.value,
            },
        });

        showDeleteDialog.value = false;
        clearSelection();
        toast.success('Leads deleted successfully');

        // Refresh leads data
        await fetchLeads();
    } catch (error) {
        console.error('Delete error:', error);
        toast.error('Failed to delete leads. Please try again.');
    } finally {
        isDeleting.value = false;
    }
};

const exportSelectedLeads = async () => {
    if (!canExportLeads.value) {
        toast.error('Permission denied', {
            description: 'You do not have permission to export leads.',
        });
        return;
    }

    if (!hasSelectedLeads.value) return;

    isExporting.value = true;

    try {
        const res = await axios.post(
            route('agents.leads.export', { agent: props.agent.external_id }),
            {
                lead_ids: selectedLeads.value,
            },
            {
                responseType: 'blob',
            },
        );

        const contentType = res.headers['content-type'];
        const blob = new Blob([res.data], { type: typeof contentType === 'string' ? contentType : 'application/octet-stream' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'leads.csv'; // fallback filename
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);

        toast.success('Leads exported successfully');
    } catch (error) {
        console.error('Export error:', error);
        toast.error('Failed to export leads. Please try again.');
    } finally {
        isExporting.value = false;
    }
};

const cancelDelete = () => {
    showDeleteDialog.value = false;
};
</script>

<template>
    <AgentLayout :agent="agent" title="Leads">
        <div class="flex justify-center">
            <Card class="w-full max-w-6xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Leads
                    </CardTitle>
                    <CardDescription> Manage leads captured by your agent </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <!-- Leads connector not attached -->
                        <div
                            v-if="!leadsConnectorAttached"
                            class="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/50 dark:bg-amber-950/30"
                        >
                            <div class="flex items-start gap-3">
                                <Plug class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-500" />
                                <div>
                                    <p class="font-medium text-amber-900 dark:text-amber-200">Leads connector isn't attached to this agent</p>
                                    <p class="mt-1 text-amber-800/80 dark:text-amber-200/80">
                                        The agent can't capture leads from chat until you attach the Leads connector. The field settings below still
                                        apply once it's enabled.
                                    </p>
                                </div>
                            </div>
                            <Button as-child variant="outline" size="sm" class="shrink-0">
                                <Link :href="connectorsUrl">Open Connectors</Link>
                            </Button>
                        </div>

                        <!-- Search and Actions Bar -->
                        <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                            <!-- Search Input -->
                            <div class="relative w-full sm:w-64">
                                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform" />
                                <Input v-model="searchQuery" placeholder="Search leads..." class="pl-10" />
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex w-full gap-2 sm:w-auto">
                                <Button @click="openSettings" variant="outline" size="sm" class="flex-1 sm:flex-none">
                                    <Settings class="mr-2 h-4 w-4" />
                                    Settings
                                </Button>
                                <Button
                                    @click="exportSelectedLeads"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!hasSelectedLeads || isExporting"
                                    class="flex-1 sm:flex-none"
                                >
                                    <LoaderCircle v-if="isExporting" class="mr-2 h-4 w-4 animate-spin" />
                                    <Download v-else class="mr-2 h-4 w-4" />
                                    {{ isExporting ? 'Exporting...' : 'Export CSV' }}
                                </Button>
                                <Button
                                    @click="openDeleteDialog"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!hasSelectedLeads"
                                    class="text-destructive hover:text-destructive flex-1 sm:flex-none"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            </div>
                        </div>

                        <!-- Results Counter -->
                        <div v-if="leads.length > 0" class="text-muted-foreground flex items-center justify-between text-sm">
                            <span>
                                Showing {{ filteredLeads.length }} of {{ leads.length }} leads
                                <span v-if="searchQuery.trim()"> (filtered) </span>
                            </span>
                        </div>

                        <!-- Leads Table -->
                        <div class="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-12">
                                            <Checkbox
                                                id="select-all"
                                                :model-value="isAllSelected"
                                                :indeterminate="isIndeterminate"
                                                @update:model-value="toggleSelectAll"
                                            />
                                        </TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Conversation</TableHead>
                                        <TableHead>Created At</TableHead>
                                        <TableHead class="w-20">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="isLoadingLeads">
                                        <TableCell colspan="6" class="py-8 text-center">
                                            <LoaderCircle class="text-muted-foreground mx-auto h-8 w-8 animate-spin" />
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-else-if="filteredLeads.length === 0">
                                        <TableCell colspan="6" class="text-muted-foreground py-8 text-center">
                                            <div v-if="leads.length === 0">
                                                <Users class="text-muted-foreground/50 mx-auto mb-4 h-12 w-12" />
                                                <p>No leads captured yet.</p>
                                                <p class="text-sm">Leads will appear here when visitors interact with your agent.</p>
                                            </div>
                                            <div v-else>
                                                <p>No leads found matching your search.</p>
                                                <p class="text-sm">Try adjusting your search terms.</p>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow
                                        v-for="lead in filteredLeads"
                                        :key="lead.id"
                                        :class="{ 'bg-muted/50': selectedLeads.includes(lead.id) }"
                                    >
                                        <TableCell>
                                            <Checkbox
                                                :id="`lead-${lead.id}`"
                                                :model-value="selectedLeads.includes(lead.id)"
                                                @update:model-value="(checked) => toggleSelectLead(lead.id, checked)"
                                            />
                                        </TableCell>
                                        <TableCell class="font-medium">
                                            {{ lead.name || 'Anonymous' }}
                                        </TableCell>
                                        <TableCell>
                                            {{ lead.email || 'No email provided' }}
                                        </TableCell>
                                        <TableCell>
                                            <Link
                                                v-if="lead.conversation_id"
                                                :href="route('agents.logs', { agent: agent.external_id, conversation_id: lead.conversation_id })"
                                                class="text-primary inline-flex items-center gap-1 text-sm hover:underline"
                                            >
                                                View Chat
                                                <ExternalLink class="h-3 w-3" />
                                            </Link>
                                            <span v-else class="text-muted-foreground text-sm">No conversation</span>
                                        </TableCell>
                                        <TableCell>
                                            {{ formatDate(lead.created_at) }}
                                        </TableCell>
                                        <TableCell>
                                            <Button variant="ghost" size="sm" @click="viewLead(lead)" class="h-8 w-8 p-0">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="showDeleteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertTriangle class="text-destructive h-5 w-5" />
                        Delete Selected Leads
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete {{ selectedLeads.length }} selected lead{{ selectedLeads.length === 1 ? '' : 's' }}? This
                        action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelDelete" :disabled="isDeleting"> Cancel </Button>
                    <Button variant="destructive" @click="deleteSelectedLeads" :disabled="isDeleting">
                        <LoaderCircle v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                        <Trash2 v-else class="mr-2 h-4 w-4" />
                        {{ isDeleting ? 'Deleting...' : 'Delete Leads' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Lead Details Sheet -->
        <LeadDetailsSheet v-model:open="showLeadDetails" :lead="selectedLeadForView" />

        <!-- Lead Settings Dialog -->
        <Dialog v-model:open="showSettingsDialog">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Lead Settings</DialogTitle>
                    <DialogDescription>
                        Pick which contact details the agent should ask for. Attach the Leads connector to this agent (Connectors tab) to enable
                        capture.
                    </DialogDescription>
                </DialogHeader>

                <!-- Display form errors at the top -->
                <FormErrors :errors="settingsForm.errors" title="Validation Error" class="mb-4" />

                <div class="space-y-6">
                    <div class="space-y-4">
                        <div class="text-sm font-medium">Fields</div>
                        <div class="overflow-hidden rounded-lg border">
                            <!-- Table Header -->
                            <div class="bg-muted/50 border-b px-4 py-3">
                                <div class="grid grid-cols-2 gap-4 text-sm font-medium">
                                    <div>Field</div>
                                    <div class="text-center">Required</div>
                                </div>
                            </div>

                            <!-- Table Rows -->
                            <div class="divide-y">
                                <div
                                    v-for="key in leadFieldOrder"
                                    :key="key"
                                    class="hover:bg-muted/30 grid grid-cols-2 items-center gap-4 px-4 py-4 transition-colors"
                                >
                                    <!-- Field Name -->
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium capitalize">
                                            {{ key.replace('_', ' ') }}
                                        </span>
                                    </div>

                                    <!-- Required Checkbox -->
                                    <div class="flex justify-center">
                                        <Checkbox :id="`field-${key}-required`" v-model="settingsForm.fields[key].required" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showSettingsDialog = false" :disabled="settingsForm.processing"> Cancel </Button>
                    <Button :disabled="settingsForm.processing" @click="saveSettings">
                        <LoaderCircle v-if="settingsForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
