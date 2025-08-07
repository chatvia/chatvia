<script lang="ts" setup>
import FormErrors from '@/components/FormErrors.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import FileUploader from '@/components/FileUploader.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertCircle, BookOpen, Bot, Brain, Database, FileText, Lightbulb, LoaderCircle, AlertTriangle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import type { UploadingFileData, FileUploaderConfig } from '@/composables/useFileUploader';

const props = defineProps<{
    can_register_custom_sub_domain: boolean;
    knowledge_based_system_instruction: string;
}>();

enum AgentType {
    INSTRUCTION_BASED = 'instruction',
    KNOWLEDGE_BASED = 'knowledge',
}

interface UsageData {
    subscription: {
        plan: string;
    };
    agents: {
        knowledge: {
            limit: number;
            created: number;
            remaining: number;
        };
        instruction: {
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
    sub_domain: '',
    system_instructions: 'You are a helpful assistant.',
    is_public: false,
    files: [] as File[],
    type: AgentType.INSTRUCTION_BASED,
});

const isSubmitting = ref(false);
const isGeneratingSubdomain = ref(false);
const isLoadingUsage = ref(false);
const usageData = ref<UsageData | null>(null);
const fileUploaderRef = ref<InstanceType<typeof FileUploader> | null>(null);

const showFileUpload = computed(() => form.type === AgentType.KNOWLEDGE_BASED);

// Compute whether user can create the selected agent type
const canCreateAgent = computed(() => {
    if (!usageData.value) return false;

    const agentType = form.type === AgentType.KNOWLEDGE_BASED ? 'knowledge' : 'instruction';
    return usageData.value.agents[agentType].remaining > 0;
});

// Check if user cannot create any agents at all
const canCreateAnyAgent = computed(() => {
    if (!usageData.value) return false;

    return usageData.value.agents.knowledge.remaining > 0 || usageData.value.agents.instruction.remaining > 0;
});

// Get current agent quota info
const currentAgentQuota = computed(() => {
    if (!usageData.value) return null;

    const agentType = form.type === AgentType.KNOWLEDGE_BASED ? 'knowledge' : 'instruction';
    return usageData.value.agents[agentType];
});

// Clear files when switching away from knowledge-based training
watch(
    () => form.type,
    (newType) => {
        if (newType === AgentType.INSTRUCTION_BASED) {
            form.files = [];
            fileUploaderRef.value?.clearAllFiles();
        } else if (newType === AgentType.KNOWLEDGE_BASED) {
            // Set the knowledge-based system instruction when switching to knowledge-based
            form.system_instructions = props.knowledge_based_system_instruction;
        }
    },
);

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

// Automatically switch to available agent type if current selection is at limit
const autoSwitchToAvailableAgentType = (showNotification = false) => {
    if (!usageData.value) return;

    const currentType = form.type === AgentType.KNOWLEDGE_BASED ? 'knowledge' : 'instruction';
    const otherType = form.type === AgentType.KNOWLEDGE_BASED ? 'instruction' : 'knowledge';

    // If current type is at limit but other type has quota, switch to it
    if (usageData.value.agents[currentType].remaining === 0 && usageData.value.agents[otherType].remaining > 0) {
        const previousType = form.type === AgentType.KNOWLEDGE_BASED ? 'knowledge-based' : 'instruction-based';
        const newType = form.type === AgentType.KNOWLEDGE_BASED ? 'instruction-based' : 'knowledge-based';

        form.type = form.type === AgentType.KNOWLEDGE_BASED ? AgentType.INSTRUCTION_BASED : AgentType.KNOWLEDGE_BASED;

        // Notify user about the automatic switch only when requested
        if (showNotification) {
            toast.info('Agent type switched', {
                description: `Switched to ${newType} agents since you've reached your ${previousType} agent limit.`,
                duration: 4000,
            });
        }
    }
};

// File uploader configuration for CreateAgent - uses analyze endpoint
const fileUploaderConfig: FileUploaderConfig = {
    maxFiles: 10,
    acceptedTypes: '.pdf,.doc,.docx,.txt,.md',
    analyzeEndpoint: (file: File) => route('agents.knowledge.files.analyze'),
    onFileAnalyzed: (file: UploadingFileData, data: any) => {
        // Update form.files with analyzed files
        updateFormFiles();
    },
    onFileError: (file: UploadingFileData, error: string) => {
        console.error('File analysis error:', error);
    }
};

const updateFormFiles = () => {
    if (fileUploaderRef.value) {
        const analyzedFiles = fileUploaderRef.value.uploadingFiles
            .filter(f => f.isAnalyzed && !f.error)
            .map(f => f.file);
        form.files = analyzedFiles;
    }
};

// Handle manual agent type selection
const selectAgentType = (targetType: AgentType) => {
    if (!usageData.value) return;

    const agentTypeKey = targetType === AgentType.KNOWLEDGE_BASED ? 'knowledge' : 'instruction';

    // Check if the target type has quota available
    if (usageData.value.agents[agentTypeKey].remaining === 0) {
        const agentTypeName = targetType === AgentType.KNOWLEDGE_BASED ? 'knowledge-based' : 'instruction-based';
        toast.error('Agent limit reached', {
            description: `You've reached your limit for ${agentTypeName} agents. Try the other agent type or upgrade your plan.`,
            duration: 4000,
        });
        return;
    }

    // Switch to the target type
    form.type = targetType;

    // Update system instructions based on the selected type
    if (targetType === AgentType.KNOWLEDGE_BASED) {
        form.system_instructions = props.knowledge_based_system_instruction;
    } else {
        form.system_instructions = 'You are a helpful assistant.';
    }
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
        const agentTypeName = form.type === AgentType.KNOWLEDGE_BASED ? 'knowledge-based' : 'instruction-based';
        toast.error('Agent limit reached', {
            description: `You've reached your limit for ${agentTypeName} agents. Please upgrade your plan or delete existing agents.`,
            duration: 5000,
        });
        return;
    }

    isSubmitting.value = true;

    if (form.type === AgentType.KNOWLEDGE_BASED && form.files.length === 0) {
        toast.error('No files', {
            description: 'Please upload at least one file to train your knowledge-based agent.',
            duration: 5000,
        });
        isSubmitting.value = false;
        return;
    }

    form.post(route('agents.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            fileUploaderRef.value?.clearAllFiles();
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

const generateNewSubdomain = () => {
    isGeneratingSubdomain.value = true;
    axios.get(route('generate-random-subdomain')).then((response) => {
        isGeneratingSubdomain.value = false;
        form.sub_domain = response.data.name;
    });
};

onMounted(() => {
    // Only auto-generate subdomain if user cannot register custom subdomains
    if (!props.can_register_custom_sub_domain) {
        generateNewSubdomain();
    }
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
                <div v-else class="bg-muted/50 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm">
                            <span class="font-medium">{{ form.type === 'knowledge' ? 'Knowledge-based' : 'Instruction-based' }} agents:</span>
                            {{ currentAgentQuota.remaining }}/{{ currentAgentQuota.limit }} remaining
                        </div>
                        <div class="text-xs text-muted-foreground">
                            {{ usageData.subscription.plan }} plan
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
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 items-start">
                                    <div class="grid gap-2">
                                        <Label for="name" required>Name</Label>
                                        <Input id="name" v-model="form.name" :error="form.errors.name" :disabled="!canCreateAnyAgent" autofocus placeholder="E.g., Meetings" />

                                        <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="domain" required>Domain</Label>
                                        <div class="flex items-center">
                                            <Input
                                                id="domain"
                                                v-model="form.sub_domain"
                                                :error="form.errors.sub_domain"
                                                :readonly="!props.can_register_custom_sub_domain"
                                                :disabled="!canCreateAnyAgent"
                                                :class="[
                                                    'rounded-r-none',
                                                    !props.can_register_custom_sub_domain ? 'disabled' : ''
                                                ]"
                                                :placeholder="props.can_register_custom_sub_domain ? 'Enter custom subdomain' : ''"
                                            />
                                            <div
                                                class="border-input bg-muted text-muted-foreground flex h-9 items-center rounded-r-md border border-l-0 px-3 text-sm"
                                            >
                                                .chatvia.ai
                                            </div>
                                            <Button
                                                :disabled="isGeneratingSubdomain || !canCreateAnyAgent"
                                                class="ml-1 h-6 w-6 p-0"
                                                size="icon"
                                                type="button"
                                                variant="ghost"
                                                @click="generateNewSubdomain"
                                            >
                                                <LoaderCircle v-if="isGeneratingSubdomain" class="h-3.5 w-3.5 animate-spin" />
                                                <svg
                                                    v-else
                                                    class="h-3.5 w-3.5"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    viewBox="0 0 24 24"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                >
                                                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2" />
                                                </svg>
                                                <span class="sr-only">Generate new subdomain</span>
                                            </Button>
                                        </div>
                                        <p v-if="form.errors.sub_domain" class="text-destructive text-sm">
                                            {{ form.errors.sub_domain }}
                                        </p>
                                        <p v-if="!props.can_register_custom_sub_domain" class="text-muted-foreground text-sm mt-1">
                                            <Link
                                                :href="route('billing.packages')"
                                                class="text-primary hover:underline font-medium"
                                            >
                                                Upgrade
                                        </Link>
                                            to create custom subdomains.
                                        </p>
                                    </div>
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
                                <!-- Instruction-based (No Training) -->
                                <div
                                    :class="[
                                        form.type === 'instruction' ? 'border-primary bg-primary/5' : 'border-border',
                                        usageData && usageData.agents.instruction.remaining === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                    ]"
                                    class="relative rounded-lg border-2 p-6 transition-all hover:shadow-md"
                                    @click="selectAgentType(AgentType.INSTRUCTION_BASED)"
                                >
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/20">
                                                <Lightbulb class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-semibold">Instruction-based</h3>
                                                <span
                                                    class="rounded-full bg-blue-100 px-2 py-1 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                                    >No Training</span
                                                >
                                                <div
                                                    v-if="form.type === 'instruction'"
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
                                                Ready-to-use agent guided by your instructions only
                                            </p>
                                            <!-- Quota indicator -->
                                            <div v-if="usageData" class="mb-3 text-xs text-muted-foreground">
                                                {{ usageData.agents.instruction.remaining }}/{{ usageData.agents.instruction.limit }} remaining
                                            </div>
                                            <div class="space-y-2">
                                                <div class="flex items-center gap-2 text-sm">
                                                    <div class="h-1.5 w-1.5 rounded-full bg-green-500"></div>
                                                    <span>Instant deployment - no training required</span>
                                                </div>
                                                <div class="flex items-center gap-2 text-sm">
                                                    <div class="h-1.5 w-1.5 rounded-full bg-green-500"></div>
                                                    <span>Uses AI's built-in knowledge</span>
                                                </div>
                                                <div class="flex items-center gap-2 text-sm">
                                                    <div class="h-1.5 w-1.5 rounded-full bg-green-500"></div>
                                                    <span>Perfect for general-purpose tasks</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- DocumentData-based Training -->
                                <div
                                    :class="[
                                        form.type === 'knowledge' ? 'border-primary bg-primary/5' : 'border-border',
                                        usageData && usageData.agents.knowledge.remaining === 0 ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                    ]"
                                    class="relative rounded-lg border-2 p-6 transition-all hover:shadow-md"
                                    @click="selectAgentType(AgentType.KNOWLEDGE_BASED)"
                                >
                                    <div class="flex items-start gap-4">
                                        <div class="flex-shrink-0">
                                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/20">
                                                <Database class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-semibold">Knowledge-based</h3>
                                                <span
                                                    class="rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                                    >With Training</span
                                                >
                                                <div
                                                    v-if="form.type === 'knowledge'"
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
                                            <p class="text-muted-foreground mt-1 mb-3 text-sm">Agent trained on your documents and knowledge base</p>
                                            <!-- Quota indicator -->
                                            <div v-if="usageData" class="mb-3 text-xs text-muted-foreground">
                                                {{ usageData.agents.knowledge.remaining }}/{{ usageData.agents.knowledge.limit }} remaining
                                            </div>
                                            <div class="space-y-2">
                                                <div class="flex items-center gap-2 text-sm">
                                                    <div class="h-1.5 w-1.5 rounded-full bg-blue-500"></div>
                                                    <span>Learns from your specific data</span>
                                                </div>
                                                <div class="flex items-center gap-2 text-sm">
                                                    <div class="h-1.5 w-1.5 rounded-full bg-blue-500"></div>
                                                    <span>Perfect for specialized knowledge</span>
                                                </div>
                                                <div class="flex items-center gap-2 text-sm">
                                                    <div class="h-1.5 w-1.5 rounded-full bg-blue-500"></div>
                                                    <span>Provides accurate, contextual answers</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>


                        </CardContent>
                    </Card>

                    <!-- Instructions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Instructions</CardTitle>
                            <CardDescription> Define how your agent should behave and respond</CardDescription>
                        </CardHeader>

                        <CardContent>
                            <div class="grid gap-2">
                                <Label for="instructions" required>System Instructions</Label>
                                <Textarea
                                    id="instructions"
                                    v-model="form.system_instructions"
                                    :error="form.errors.system_instructions"
                                    :disabled="!canCreateAnyAgent"
                                                                    :placeholder="
                                    form.type === AgentType.KNOWLEDGE_BASED
                                        ? 'Enter system instructions for your knowledge-based agent...'
                                        : 'You are a helpful assistant that specializes in customer support. Be friendly, professional, and always aim to solve problems efficiently...'
                                "
                                    rows="6"
                                />
                                <p v-if="form.errors.system_instructions" class="text-destructive text-sm">
                                    {{ form.errors.system_instructions }}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    These instructions tell the AI how to behave, what tone to use, and what its purpose is
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <div v-if="showFileUpload" :class="{ 'opacity-50 pointer-events-none': !canCreateAnyAgent }">
                        <FileUploader
                            ref="fileUploaderRef"
                            :config="fileUploaderConfig"
                            title="Knowledge Base"
                            description="Upload documents to train your agent. Your agent will learn from these files to provide specialized knowledge and accurate answers."
                            @files-changed="updateFormFiles"
                        />

                        <!-- Knowledge base extension note -->
                        <div class="mt-4 text-muted-foreground flex items-center gap-2 text-sm">
                            <p>You can extend your AI Agent's knowledge base later by adding or removing files from this folder.</p>
                        </div>

                        <p v-if="form.errors.files" class="text-destructive text-sm mt-2">{{ form.errors.files }}</p>
                    </div>

                    <!-- Visibility Settings -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Visibility Settings</CardTitle>
                            <CardDescription> Control who can access your agent</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-between space-x-2">
                                <div>
                                    <Label class="text-base" for="is_public">Make this agent public</Label>
                                    <p class="text-muted-foreground text-sm">Public agents can be accessed by anyone with the link</p>
                                </div>
                                <Switch id="is_public" v-model="form.is_public" :disabled="!canCreateAnyAgent" />
                            </div>
                            <div class="border-border mt-4 border-t pt-4">
                                <div class="flex items-center gap-2">
                                    <AlertCircle class="text-muted-foreground h-4 w-4" />
                                    <p class="text-muted-foreground text-sm">
                                        After creating your agent, you'll be able to invite specific users by email address to access your agent, even
                                        if it's not public.
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Important Note -->
                    <Alert class="bg-amber-50 text-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
                        <AlertCircle class="h-4 w-4" />
                        <AlertTitle>Important</AlertTitle>
                        <AlertDescription v-if="form.type === 'knowledge'">
                            Your agent will be trained on the documents you upload. Make sure they don't contain sensitive or confidential information
                            you don't want to share. The uploaded files will be processed and stored securely to train your agent.
                        </AlertDescription>
                        <AlertDescription v-else>
                            Your agent works without any training and uses only system instructions. It relies on the AI's pre-existing knowledge, so
                            make sure your instructions are clear and detailed to get the best performance.
                        </AlertDescription>
                    </Alert>

                    <!-- Submit Button -->
                    <CardFooter class="flex justify-end border-t p-6">
                        <Button :disabled="isSubmitting || !canCreateAnyAgent" size="lg" type="submit">
                            <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            Create Agent
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

/* File upload area */
.upload-area {
    transition: all 0.2s ease;
}

.upload-area:hover {
    border-color: hsl(var(--primary));
    background-color: hsl(var(--primary) / 0.05);
}

/* File list item hover */
.file-item {
    transition: background-color 0.2s ease;
}

.file-item:hover {
    background-color: hsl(var(--muted) / 0.5);
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
