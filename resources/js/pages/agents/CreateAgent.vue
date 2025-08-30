<script lang="ts" setup>
import FormErrors from '@/components/FormErrors.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertCircle, Bot, Brain, MessageSquare, Globe, LoaderCircle, AlertTriangle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    can_register_custom_sub_domain: boolean;
    knowledge_based_system_instruction: string;
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

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Agents',
        href: route('agents.index'),
    },
    {
        title: 'Create Agent',
        href: route('agents.create'),
    },
];

const form = useForm({
    name: '',
    type: AgentType.CHATGPT_STYLE,
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



// Handle manual agent type selection
const selectAgentType = (targetType: AgentType) => {
    if (!usageData.value) return;

    // Check if quota is available (unified limit)
    if (usageData.value.agents.total.remaining === 0) {
        toast.error('Agent limit reached', {
            description: 'You\'ve reached your agent limit. Please upgrade your plan or delete existing agents.',
            duration: 4000,
        });
        return;
    }

    // Switch to the target type
    form.type = targetType;
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

    if (!canCreateAgent.value) {
        toast.error('Agent limit reached', {
            description: 'You\'ve reached your agent limit. Please upgrade your plan or delete existing agents.',
            duration: 5000,
        });
        return;
    }

    isSubmitting.value = true;

    form.post(route('agents.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            // Refresh usage data after successful creation
            fetchUsageData();
        },
        onError: () => {
            isSubmitting.value = false;

            window.scrollTo({
                top: 0,
                behavior: 'smooth',
            });
        },
    });
};



onMounted(() => {
    fetchUsageData();
});
</script>

<template>
    <Head title="Create Agent" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto max-w-4xl py-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold tracking-tight">Create Agent</h1>
                <p class="text-muted-foreground mt-2">Create an AI agent that learns from your data and knowledge</p>
            </div>

            <!-- Usage Quota Alert -->
            <div v-if="usageData && currentAgentQuota" class="mb-6">
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
                <div class="grid gap-8" :class="{ 'opacity-50 pointer-events-none': !canCreateAnyAgent }">
                    <!-- Basic Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Bot class="h-5 w-5" />
                                Basic Information
                            </CardTitle>
                            <CardDescription> Define your agent's identity and purpose</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-6">
                                <div class="grid gap-2">
                                    <Label for="name" required>Name</Label>
                                    <Input id="name" v-model="form.name" :error="form.errors.name" :disabled="!canCreateAnyAgent" autofocus placeholder="E.g., Customer Support Agent" />

                                    <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Training Type Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Brain class="h-5 w-5" />
                                Agent Type
                            </CardTitle>
                            <CardDescription>Choose how your AI agent will work</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 md:grid-cols-2">
                                <!-- Widget -->
                                <div
                                    :class="[
                                        form.type === AgentType.WIDGET ? 'border-primary bg-primary/5' : 'border-border',
                                        usageData && usageData.agents.total.remaining === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                    ]"
                                    class="relative rounded-lg border-2 p-6 transition-all hover:shadow-md"
                                    @click="selectAgentType(AgentType.WIDGET)"
                                >
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                                                <Globe class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-semibold">Widget</h3>
                                                <span
                                                    class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                                    >Embeddable</span
                                                >
                                                <div
                                                    v-if="form.type === AgentType.WIDGET"
                                                    class="bg-primary flex h-5 w-5 items-center justify-center rounded-full"
                                                >
                                                    <svg class="text-primary-foreground h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path
                                                            clip-rule="evenodd"
                                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                            fill-rule="evenodd"
                                                        />
                                                    </svg>
                                                </div>
                                            </div>
                                            <p class="text-muted-foreground mt-1 mb-3 text-sm">
                                                Chat widget that can be embedded on your website
                                            </p>

                                            <div class="space-y-2">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ChatGPT-style -->
                                <div
                                    :class="[
                                        form.type === AgentType.CHATGPT_STYLE ? 'border-primary bg-primary/5' : 'border-border',
                                        usageData && usageData.agents.total.remaining === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                    ]"
                                    class="relative rounded-lg border-2 p-6 transition-all hover:shadow-md"
                                    @click="selectAgentType(AgentType.CHATGPT_STYLE)"
                                >
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/20">
                                                <MessageSquare class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-semibold">ChatGPT-style</h3>
                                                <span
                                                    class="rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                                    >Team Access</span
                                                >
                                                <div
                                                    v-if="form.type === AgentType.CHATGPT_STYLE"
                                                    class="bg-primary flex h-5 w-5 items-center justify-center rounded-full"
                                                >
                                                    <svg class="text-primary-foreground h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path
                                                            clip-rule="evenodd"
                                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                            fill-rule="evenodd"
                                                        />
                                                    </svg>
                                                </div>
                                            </div>
                                            <p class="text-muted-foreground mt-1 mb-3 text-sm">ChatGPT-like interface that can be shared with your team.</p>

                                            <div class="space-y-2">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>


                        </CardContent>
                    </Card>







                    <!-- Submit Button -->
                    <CardFooter class="flex justify-end border-t p-6">
                        <Button :disabled="isSubmitting || !canCreateAnyAgent" size="lg" type="submit">
                            <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            Create
                        </Button>
                    </CardFooter>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.required:after {
    content: '*';
    color: #ef4444;
    margin-left: 2px;
}

/* Card hover effects */
.card {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow:
        0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}



/* Button transitions */
button {
    transition: all 0.2s ease;
}

/* Form field focus styles */
input:focus-visible,
textarea:focus-visible,
select:focus-visible {
    outline: 2px solid hsl(var(--primary) / 0.5);
    outline-offset: 2px;
}

/* Alert styling */
.custom-alert {
    border-left: 4px solid hsl(var(--warning));
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
}
</style>
