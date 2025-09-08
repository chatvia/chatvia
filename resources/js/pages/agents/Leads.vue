<script lang="ts" setup>
import { ref, computed, watch } from 'vue';
import { router, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import FormErrors from '@/components/FormErrors.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle
} from '@/components/ui/dialog';
import {
    Users,
    Search,
    Trash2,
    Download,
    LoaderCircle,
    AlertTriangle,
    ExternalLink,
    Eye,
    Settings
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import LeadDetailsSheet from '@/components/LeadDetailsSheet.vue';

interface Lead {
    id: number;
    name: string | null;
    email: string | null;
    phone?: string | null;
    job_title?: string | null;
    company?: string | null;
    location?: string | null;
    message?: string | null;
    referrer_url?: string | null;
    page_url?: string | null;
    locale?: string | null;
    country?: string | null;
    ip_address?: string | null;
    user_agent?: string | null;
    status?: string;
    status_label?: string;
    custom_fields?: Record<string, any>;
    created_at: string;
    contacted_at?: string | null;
    qualified_at?: string | null;
    converted_at?: string | null;
    conversation_id: string;
    conversation?: {
        id: string;
    };
}

interface Props {
    agent: any;
    leads: Lead[];
    settings?: {
        fields?: any;
    } | null;
}

const props = defineProps<Props>();

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
                    enabled: Boolean(settingValue.enabled ?? defaultField.enabled),
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

// Get dynamic form title from settings
const getFormTitleFromSettings = (): string => {
    const leadsSettings = props.settings?.fields; // This is settings('leads')
    return leadsSettings?.title || '';
};

// State
const searchQuery = ref('');
const selectedLeads = ref<number[]>([]);
const showDeleteDialog = ref(false);
const isDeleting = ref(false);
const isExporting = ref(false);
const showLeadDetails = ref(false);
const selectedLeadForView = ref<Lead | null>(null);

// Settings dialog state
const showSettingsDialog = ref(false);
type LeadFieldKey = 'name' | 'email' | 'phone' | 'job_title' | 'company' | 'location' | 'message';
type LeadFieldConfig = { title: string; required: boolean; enabled: boolean };
const leadFieldOrder: LeadFieldKey[] = ['name', 'email', 'phone', 'job_title', 'company', 'location', 'message'];

// Create default field configuration when no settings exist
const createDefaultLeadField = (key: LeadFieldKey): LeadFieldConfig => {
    const defaults: Record<LeadFieldKey, LeadFieldConfig> = {
        name: { title: 'Name', required: false, enabled: true },
        email: { title: 'Email', required: false, enabled: true },
        phone: { title: 'Phone', required: false, enabled: false },
        job_title: { title: 'Job Title', required: false, enabled: false },
        company: { title: 'Company', required: false, enabled: false },
        location: { title: 'Location', required: false, enabled: false },
        message: { title: 'Message', required: false, enabled: false },
    };
    return defaults[key];
};

// Inertia form for settings
const settingsForm = useForm({
    collect_leads: false as boolean,
    title: getFormTitleFromSettings(),
    fields: getLeadFieldsFromSettings()
});

// Watch for changes to collect_leads to automatically enable name and email
watch(() => settingsForm.collect_leads, (newValue) => {
    if (newValue) {
        // When lead collection is enabled, name and email must be enabled and required
        settingsForm.fields.name.required = true;
        settingsForm.fields.name.enabled = true;
        settingsForm.fields.email.required = true;
        settingsForm.fields.email.enabled = true;
    }
});

// Computed properties
const filteredLeads = computed(() => {
    if (!searchQuery.value.trim()) return props.leads;

    const query = searchQuery.value.toLowerCase();
    return props.leads.filter(lead =>
        (lead.name?.toLowerCase().includes(query)) ||
        (lead.email?.toLowerCase().includes(query))
    );
});

const isAllSelected = computed(() => {
    return filteredLeads.value.length > 0 &&
           filteredLeads.value.every(lead => selectedLeads.value.includes(lead.id));
});

const isIndeterminate = computed(() => {
    const selectedCount = filteredLeads.value.filter(lead => selectedLeads.value.includes(lead.id)).length;
    return selectedCount > 0 && selectedCount < filteredLeads.value.length;
});

const hasSelectedLeads = computed(() => selectedLeads.value.length > 0);

// Methods
const toggleSelectAll = (checked: boolean | "indeterminate") => {
    const isChecked = checked === true;
    
    if (isChecked) {
        // Select all visible leads
        const visibleIds = filteredLeads.value.map(lead => lead.id);
        const newSelection = [...new Set([...selectedLeads.value, ...visibleIds])];
        selectedLeads.value = newSelection;
    } else {
        // Deselect all visible leads
        selectedLeads.value = selectedLeads.value.filter(id =>
            !filteredLeads.value.some(lead => lead.id === id)
        );
    }
};

const toggleSelectLead = (leadId: number, checked: boolean | "indeterminate") => {
    const isChecked = checked === true;
    
    if (isChecked) {
        if (!selectedLeads.value.includes(leadId)) {
            selectedLeads.value.push(leadId);
        }
    } else {
        selectedLeads.value = selectedLeads.value.filter(id => id !== leadId);
    }
};

const clearSelection = () => {
    selectedLeads.value = [];
};

const viewLead = (lead: Lead) => {
    selectedLeadForView.value = lead;
    showLeadDetails.value = true;
};

const saveSettings = () => {
    settingsForm.patch(route('agents.leads.settings', { agent: (props as any).agent.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showSettingsDialog.value = false;
            toast.success('Lead settings saved');
            // Clear any errors on successful save
            settingsForm.clearErrors();
        },
        onError: (errors) => {
            console.error('Form errors:', errors);
            // Errors will be displayed inline via FormErrors component and individual field errors
        },
    });
};

const openSettings = () => {
    // Clear any previous errors when opening the dialog
    settingsForm.clearErrors();
    
    // Initialize from props.agent
    settingsForm.collect_leads = Boolean(props.agent?.collect_leads);
    
    // Get current settings from the dynamic functions
    settingsForm.title = getFormTitleFromSettings();
    settingsForm.fields = getLeadFieldsFromSettings();

    // If lead collection is enabled, ensure name and email are always enabled and required
    if (settingsForm.collect_leads) {
        settingsForm.fields.name.required = true;
        settingsForm.fields.name.enabled = true;
        settingsForm.fields.email.required = true;
        settingsForm.fields.email.enabled = true;
    }

    showSettingsDialog.value = true;
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const openDeleteDialog = () => {
    if (!hasSelectedLeads.value) return;
    showDeleteDialog.value = true;
};

const deleteSelectedLeads = () => {
    if (!hasSelectedLeads.value) return;

    isDeleting.value = true;

    router.delete(route('agents.leads.delete', { agent: props.agent.id }), {
        data: {
            lead_ids: selectedLeads.value
        },
        preserveScroll: true,
        onSuccess: () => {
            isDeleting.value = false;
            showDeleteDialog.value = false;
            clearSelection();
            toast.success('Leads deleted successfully');
        },
        onError: (errors) => {
            isDeleting.value = false;
            toast.error('Failed to delete leads. Please try again.');
        }
    });
};

const exportSelectedLeads = async () => {
    if (!hasSelectedLeads.value) return;

    isExporting.value = true;

    try {
        const res = await axios.post(route('agents.leads.export', { agent: props.agent.id }), {
            lead_ids: selectedLeads.value
        }, { 
            responseType: 'blob' 
        });

        const blob = new Blob([res.data], { type: res.headers['content-type'] ?? 'application/octet-stream' });
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
                    <CardDescription>
                        Manage leads captured by your agent
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <!-- Search and Actions Bar -->
                        <div class="flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                            <!-- Search Input -->
                            <div class="relative w-full sm:w-64">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Search leads..."
                                    class="pl-10"
                                />
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2 w-full sm:w-auto">
                                <Button
                                    @click="openSettings"
                                    variant="outline"
                                    size="sm"
                                    class="flex-1 sm:flex-none"
                                >
                                    <Settings class="h-4 w-4 mr-2" />
                                    Settings
                                </Button>
                                <Button
                                    @click="exportSelectedLeads"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!hasSelectedLeads || isExporting"
                                    class="flex-1 sm:flex-none"
                                >
                                    <LoaderCircle v-if="isExporting" class="h-4 w-4 mr-2 animate-spin" />
                                    <Download v-else class="h-4 w-4 mr-2" />
                                    {{ isExporting ? 'Exporting...' : 'Export CSV' }}
                                </Button>
                                <Button
                                    @click="openDeleteDialog"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!hasSelectedLeads"
                                    class="text-destructive hover:text-destructive flex-1 sm:flex-none"
                                >
                                    <Trash2 class="h-4 w-4 mr-2" />
                                    Delete
                                </Button>
                            </div>
                        </div>

                        <!-- Results Counter -->
                        <div v-if="props.leads.length > 0" class="flex justify-between items-center text-sm text-muted-foreground">
                            <span>
                                Showing {{ filteredLeads.length }} of {{ props.leads.length }} leads
                                <span v-if="searchQuery.trim()">
                                    (filtered)
                                </span>
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
                    <TableRow v-if="filteredLeads.length === 0">
                        <TableCell colspan="6" class="text-center py-8 text-muted-foreground">
                                            <div v-if="props.leads.length === 0">
                                                <Users class="h-12 w-12 mx-auto mb-4 text-muted-foreground/50" />
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
                                @update:model-value="checked => toggleSelectLead(lead.id, checked)"
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
                                :href="route('agents.logs', { agent: agent.id, conversation_id: lead.conversation_id })"
                                class="inline-flex items-center gap-1 text-primary hover:underline text-sm"
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
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="viewLead(lead)"
                                class="h-8 w-8 p-0"
                            >
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
                        <AlertTriangle class="h-5 w-5 text-destructive" />
                        Delete Selected Leads
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete {{ selectedLeads.length }} selected lead{{ selectedLeads.length === 1 ? '' : 's' }}?
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelDelete" :disabled="isDeleting">
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        @click="deleteSelectedLeads"
                        :disabled="isDeleting"
                    >
                        <LoaderCircle v-if="isDeleting" class="h-4 w-4 mr-2 animate-spin" />
                        <Trash2 v-else class="h-4 w-4 mr-2" />
                        {{ isDeleting ? 'Deleting...' : 'Delete Leads' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Lead Details Sheet -->
        <LeadDetailsSheet
            v-model:open="showLeadDetails"
            :lead="selectedLeadForView"
        />

        <!-- Lead Settings Dialog -->
        <Dialog v-model:open="showSettingsDialog">
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Lead Settings</DialogTitle>
                    <DialogDescription>
                        Enable lead collection and customize field titles and required flags.
                    </DialogDescription>
                </DialogHeader>

                <!-- Display form errors at the top -->
                <FormErrors :errors="settingsForm.errors" title="Validation Error" class="mb-4" />

                <div class="space-y-6">
                    <div class="flex items-center gap-3">
                        <Checkbox 
                            id="collect-leads"
                            v-model="settingsForm.collect_leads" 
                        />
                        <label for="collect-leads" class="text-sm cursor-pointer">Enable lead collection</label>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Form Title</label>
                        <Input 
                            v-model="settingsForm.title" 
                            placeholder="e.g. Contact details" 
                            :disabled="!settingsForm.collect_leads"
                        />
                    </div>

                    <div class="space-y-4">
                        <div class="text-sm font-medium">Fields</div>
                        <div class="border rounded-lg overflow-hidden" :class="{ 'opacity-50 pointer-events-none': !settingsForm.collect_leads }">
                            <!-- Table Header -->
                            <div class="bg-muted/50 px-4 py-3 border-b">
                                <div class="grid grid-cols-3 gap-4 text-sm font-medium">
                                    <div>Field</div>
                                    <div class="text-center">Enabled</div>
                                    <div class="text-center">Required</div>
                                </div>
                            </div>
                            
                            <!-- Table Rows -->
                            <div class="divide-y">
                                <div v-for="key in leadFieldOrder" :key="key" 
                                     class="px-4 py-4 grid grid-cols-3 gap-4 items-center hover:bg-muted/30 transition-colors"
                                     :class="{ 'bg-blue-50/50 dark:bg-blue-950/20': settingsForm.collect_leads && (key === 'name' || key === 'email') }">
                                    <!-- Field Name -->
                                    <div class="flex flex-col">
                                        <span class="font-medium text-sm capitalize">
                                            {{ key.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    
                                    <!-- Enabled Checkbox -->
                                    <div class="flex justify-center">
                                        <Checkbox 
                                            :id="`field-${key}-enabled`"
                                            v-model="settingsForm.fields[key].enabled" 
                                            :disabled="!settingsForm.collect_leads || (key === 'name' || key === 'email')" 
                                        />
                                    </div>
                                    
                                    <!-- Required Checkbox -->
                                    <div class="flex justify-center">
                                        <Checkbox 
                                            :id="`field-${key}-required`"
                                            v-model="settingsForm.fields[key].required" 
                                            :disabled="!settingsForm.collect_leads || !settingsForm.fields[key].enabled || (key === 'name' || key === 'email')" 
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button 
                        variant="outline" 
                        @click="showSettingsDialog = false" 
                        :disabled="settingsForm.processing"
                    >
                        Cancel
                    </Button>
                    <Button :disabled="settingsForm.processing" @click="saveSettings">
                        <LoaderCircle v-if="settingsForm.processing" class="h-4 w-4 mr-2 animate-spin" />
                        Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
