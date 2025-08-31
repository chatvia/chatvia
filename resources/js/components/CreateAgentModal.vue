<script lang="ts" setup>
import FormErrors from '@/components/FormErrors.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertCircle, Bot, MessageSquare, Globe, LoaderCircle, AlertTriangle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    open: boolean;
    can_register_custom_sub_domain: boolean;
    knowledge_based_system_instruction: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'agent-created': [];
}>();

enum AgentType {
    WIDGET = 'widget',
    CHATGPT_STYLE = 'knowledge_base',
}

interface UsageData {
    subscription: {
        plan: string;
    };
    agents: {
        total: {
            limit: number;
            created: number;
            remaining: number;
        };
    };
    pages: {
        limit: number;
        used: number;
        remaining: number;
    };
    messages: {
        limit: number;
        sent: number;
        remaining: number;
    };
    cycle: {
        started_at: string;
        ends_at: string;
    };
}

const form = useForm({
    name: '',
    type: '',
});

const isSubmitting = ref(false);
const isLoadingUsage = ref(false);
const usageData = ref<UsageData | null>(null);

// Compute whether user can create any agents
const canCreateAgent = computed(() => {
    if (!usageData.value) return false;
    return usageData.value.agents.total.remaining > 0;
});

// Check if user cannot create any agents at all (same as above now)
const canCreateAnyAgent = computed(() => {
    if (!usageData.value) return false;
    return usageData.value.agents.total.remaining > 0;
});

// Get current agent quota info
const currentAgentQuota = computed(() => {
    if (!usageData.value) return null;
    return usageData.value.agents.total;
});

// Get display name for selected agent type
const selectedAgentTypeName = computed(() => {
    if (form.type === AgentType.CHATGPT_STYLE) return 'ChatGPT-style';
    if (form.type === AgentType.WIDGET) return 'Website widget';
    return '';
});

// Fetch usage data
const fetchUsageData = async () => {
    try {
        isLoadingUsage.value = true;
        const response = await axios.get(route('usage'));
        usageData.value = response.data;

        // Auto-switch to available agent type if current selection is at limit
        autoSwitchToAvailableAgentType();
    } catch (error) {
        console.error('Failed to fetch usage data:', error);
        toast.error('Failed to load usage information');
    } finally {
        isLoadingUsage.value = false;
    }
};

// No longer needed with unified limit
const autoSwitchToAvailableAgentType = (showNotification = false) => {
    // With unified limit, no need to switch between types
    return;
};



const submit = () => {
    // Check quota before submitting
    if (!canCreateAnyAgent.value) {
        toast.error('Agent limit reached', {
            description: 'You\'ve reached your agent limit. Please upgrade your plan or delete existing agents to create new ones.',
            duration: 5000,
        });
        return;
    }

    isSubmitting.value = true;

    form.post(route('agents.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            // Close modal and refresh usage data after successful creation
            emit('update:open', false);
            emit('agent-created');
            fetchUsageData();
            // Reset form
            form.reset();
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const closeModal = () => {
    emit('update:open', false);
    form.reset();
};

// Watch for modal open state to fetch usage data
watch(() => props.open, (newValue) => {
    if (newValue) {
        fetchUsageData();
    }
});

onMounted(() => {
    if (props.open) {
        fetchUsageData();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Bot class="h-5 w-5" />
                    Create Agent
                </DialogTitle>
                <DialogDescription>
                    Create an AI agent that learns from your data and knowledge
                </DialogDescription>
            </DialogHeader>

            <!-- Usage Quota Alert -->
            <div v-if="usageData && currentAgentQuota">
                <div v-if="!canCreateAnyAgent" class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/20">
                    <div class="flex items-start gap-3">
                        <AlertTriangle class="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" />
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Agent Limit Reached</h3>
                            <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                                You've reached your agent limit. Please upgrade your plan or delete existing agents to create new ones.
                            </p>
                            <div class="mt-3">
                                <Button
                                    size="sm"
                                    class="bg-red-600 text-white hover:bg-red-700 dark:bg-red-600 dark:hover:bg-red-700"
                                    @click="router.visit(route('billing.index'))"
                                >
                                    Upgrade Plan
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <FormErrors :errors="form.errors" class="mb-6" />

            <form @submit.prevent="submit">
                <progress v-if="form.progress" :value="form.progress.percentage" max="100">{{ form.progress.percentage }}%</progress>
                <div class="space-y-6" :class="{ 'opacity-50 pointer-events-none': !canCreateAnyAgent }">
                    <!-- Agent Name -->
                    <div class="space-y-2">
                        <Label for="name" required>Agent Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            :error="form.errors.name"
                            :disabled="!canCreateAnyAgent"
                            autofocus
                            placeholder="E.g., Customer Support Agent"
                        />
                        <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                    </div>

                    <!-- Agent Type -->
                    <div class="space-y-2">
                        <Label for="type" required>Agent Type</Label>
                        <Select v-model="form.type" :disabled="!canCreateAnyAgent">
                            <SelectTrigger class="w-full">
                                <SelectValue>
                                    <template #default>
                                        <span>{{ selectedAgentTypeName || 'Select agent type' }}</span>
                                    </template>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="AgentType.CHATGPT_STYLE">
                                    <div class="flex items-center gap-2">
                                        <MessageSquare class="h-4 w-4 text-emerald-600" />
                                        <span>ChatGPT-style</span>
                                        <span class="ml-auto text-xs text-muted-foreground">Team Access</span>
                                    </div>
                                </SelectItem>
                                <SelectItem :value="AgentType.WIDGET">
                                    <div class="flex items-center gap-2">
                                        <Globe class="h-4 w-4 text-blue-600" />
                                        <span>Website widget</span>
                                        <span class="ml-auto text-xs text-muted-foreground">Embeddable</span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.type" class="text-destructive text-sm">{{ form.errors.type }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ form.type === AgentType.CHATGPT_STYLE
                                ? 'ChatGPT-like interface that can be shared with your team.'
                                : 'Chat widget that can be embedded on your website.'
                            }}
                        </p>
                    </div>
                </div>
            </form>

            <DialogFooter class="flex justify-between">
                <Button variant="outline" @click="closeModal">
                    Cancel
                </Button>
                <Button :disabled="isSubmitting || !canCreateAnyAgent" size="lg" @click="submit">
                    <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                    Create Agent
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
