<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle, Bot, Globe, LoaderCircle, MessageSquare } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'agent-created': [];
}>();

enum AgentType {
    WIDGET = 'widget',
    CHATGPT_STYLE = 'knowledge_base',
}

interface AgentTypeOption {
    value: AgentType;
    label: string;
    description: string;
    icon: typeof Globe;
    iconColor: string;
    bgColor: string;
    borderColor: string;
}

const agentTypeOptions: AgentTypeOption[] = [
    {
        value: AgentType.WIDGET,
        label: 'Widget',
        description: 'Embeddable chat widget for your website, perfect for customer support',
        icon: Globe,
        iconColor: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-50 dark:bg-blue-950/20',
        borderColor: 'border-blue-500',
    },
    {
        value: AgentType.CHATGPT_STYLE,
        label: 'Knowledge Base',
        description: 'ChatGPT-style conversational AI for your team and knowledge management',
        icon: MessageSquare,
        iconColor: 'text-emerald-600 dark:text-emerald-400',
        bgColor: 'bg-emerald-50 dark:bg-emerald-950/20',
        borderColor: 'border-emerald-500',
    },
];

type UsageData = App.Auth.Data.Resources.My.Usage.UsageResourceData;

const form = useForm({
    name: '',
    type: '',
});

const isSubmitting = ref(false);
const isLoadingUsage = ref(false);
const usageData = ref<UsageData | null>(null);

// Compute whether user can create any agents
const _canCreateAgent = computed(() => {
    if (!usageData.value) return false;
    return usageData.value.agents.remaining > 0;
});

// Check if user cannot create any agents at all (same as above now)
const canCreateAnyAgent = computed(() => {
    if (!usageData.value) return false;
    return usageData.value.agents.remaining > 0;
});

// Get current agent quota info
const currentAgentQuota = computed(() => {
    if (!usageData.value) return null;
    return usageData.value.agents;
});

// Dynamic placeholder based on agent type
const namePlaceholder = computed(() => {
    switch (form.type) {
        case AgentType.WIDGET:
            return 'E.g., Customer Support Widget';
        case AgentType.CHATGPT_STYLE:
            return 'E.g., Company Knowledge Base';
        default:
            return 'Enter agent name';
    }
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
const autoSwitchToAvailableAgentType = (_showNotification = false) => {
    // With unified limit, no need to switch between types
    return;
};

const submit = () => {
    // Check quota before submitting
    if (!canCreateAnyAgent.value) {
        toast.error('Agent limit reached', {
            description: "You've reached your agent limit.",
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
            // Reset form and clear errors
            form.reset();
            form.clearErrors();
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const closeModal = () => {
    emit('update:open', false);
    form.reset();
    form.clearErrors();
};

// Watch for modal open state to fetch usage data
watch(
    () => props.open,
    (newValue) => {
        if (newValue) {
            fetchUsageData();
            // Clear any previous errors when opening modal
            form.clearErrors();
        }
    },
);

onMounted(() => {
    if (props.open) {
        fetchUsageData();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Bot class="h-5 w-5" />
                    Create Agent
                </DialogTitle>
                <DialogDescription> Create an AI agent that learns from your data and knowledge </DialogDescription>
            </DialogHeader>

            <!-- Usage Quota Alert -->
            <div v-if="usageData && currentAgentQuota">
                <div v-if="!canCreateAnyAgent" class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/20">
                    <div class="flex items-start gap-3">
                        <AlertTriangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" />
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Agent Limit Reached</h3>
                            <p class="mt-1 text-sm text-red-700 dark:text-red-300">You've reached your agent limit.</p>
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

            <form @submit.prevent="submit">
                <progress v-if="form.progress" :value="form.progress.percentage" max="100">{{ form.progress.percentage }}%</progress>
                <div class="space-y-6" :class="{ 'pointer-events-none opacity-50': !canCreateAnyAgent }">
                    <!-- Agent Name -->
                    <div class="space-y-2">
                        <Label for="name" required>Agent Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            :error="form.errors.name"
                            :disabled="!canCreateAnyAgent"
                            autofocus
                            :placeholder="namePlaceholder"
                        />
                        <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                    </div>

                    <!-- Agent Type -->
                    <div class="space-y-3">
                        <Label required>Choose Agent Type</Label>

                        <div class="space-y-3">
                            <div
                                v-for="option in agentTypeOptions"
                                :key="option.value"
                                :class="[
                                    'relative flex cursor-pointer items-start gap-4 rounded-lg border-2 p-4 transition-all duration-200 hover:shadow-md',
                                    form.type === option.value
                                        ? `${option.borderColor} ${option.bgColor}`
                                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600',
                                    !canCreateAnyAgent && 'cursor-not-allowed opacity-50',
                                ]"
                                @click="!canCreateAnyAgent ? null : (form.type = option.value)"
                            >
                                <div
                                    :class="['shrink-0 rounded-lg p-3', form.type === option.value ? option.bgColor : 'bg-gray-100 dark:bg-gray-800']"
                                >
                                    <component
                                        :is="option.icon"
                                        :class="['h-6 w-6', form.type === option.value ? option.iconColor : 'text-gray-600 dark:text-gray-400']"
                                    />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h3 class="text-base font-semibold">
                                        {{ option.label }}
                                    </h3>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        {{ option.description }}
                                    </p>
                                </div>

                                <!-- Checkmark for selected state -->
                                <div v-if="form.type === option.value" class="shrink-0">
                                    <div
                                        :class="[
                                            'flex h-6 w-6 items-center justify-center rounded-full',
                                            option.value === AgentType.WIDGET && 'bg-blue-500',
                                            option.value === AgentType.CHATGPT_STYLE && 'bg-emerald-500',
                                        ]"
                                    >
                                        <svg class="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-if="form.errors.type" class="text-destructive text-sm">{{ form.errors.type }}</p>
                    </div>
                </div>
            </form>

            <div class="my-4 border-t border-gray-200 dark:border-gray-700"></div>

            <DialogFooter class="flex justify-between">
                <Button variant="outline" size="lg" @click="closeModal"> Cancel </Button>
                <Button :disabled="isSubmitting || !canCreateAnyAgent" size="lg" @click="submit">
                    <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                    Create Agent
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
