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
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertCircle, BookOpen, Bot, Brain, Database, FileText, Lightbulb, LoaderCircle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import type { UploadingFileData, FileUploaderConfig } from '@/composables/useFileUploader';

enum AgentType {
    INSTRUCTION_BASED = 'instruction',
    KNOWLEDGE_BASED = 'knowledge',
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
const fileUploaderRef = ref<InstanceType<typeof FileUploader> | null>(null);

const showFileUpload = computed(() => form.type === AgentType.KNOWLEDGE_BASED);

// Clear files when switching away from knowledge-based training
watch(
    () => form.type,
    (newType) => {
        if (newType === AgentType.INSTRUCTION_BASED) {
            form.files = [];
            fileUploaderRef.value?.clearAllFiles();
        }
    },
);

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



const submit = () => {
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
    generateNewSubdomain();
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

            <FormErrors :errors="form.errors" class="mb-6" />

            <form @submit.prevent="submit">
                <progress v-if="form.progress" :value="form.progress.percentage" max="100">{{ form.progress.percentage }}%</progress>
                <div class="grid gap-8">
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
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label for="name" required>Name</Label>
                                        <Input id="name" v-model="form.name" :error="form.errors.name" autofocus placeholder="E.g., Meetings" />

                                        <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="domain" required>Domain</Label>
                                        <div class="flex items-center">
                                            <Input
                                                id="domain"
                                                v-model="form.sub_domain"
                                                :error="form.errors.sub_domain"
                                                :readonly="true"
                                                class="disabled rounded-r-none"
                                                placeholder=""
                                            />
                                            <div
                                                class="border-input bg-muted text-muted-foreground flex h-9 items-center rounded-r-md border border-l-0 px-3 text-sm"
                                            >
                                                .chatvia.ai
                                            </div>
                                            <Button
                                                :disabled="isGeneratingSubdomain"
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
                                    :class="form.type === 'instruction' ? 'border-primary bg-primary/5' : 'border-border'"
                                    class="relative cursor-pointer rounded-lg border-2 p-6 transition-all hover:shadow-md"
                                    @click="form.type = AgentType.INSTRUCTION_BASED"
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
                                    :class="form.type === 'knowledge' ? 'border-primary bg-primary/5' : 'border-border'"
                                    class="relative cursor-pointer rounded-lg border-2 p-6 transition-all hover:shadow-md"
                                    @click="form.type = AgentType.KNOWLEDGE_BASED"
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

                            <!-- Agent Type Description -->
                            <div class="bg-muted/50 mt-6 rounded-lg p-4">
                                <div class="flex items-start gap-3">
                                    <BookOpen class="text-muted-foreground mt-0.5 h-5 w-5 flex-shrink-0" />
                                    <div class="text-sm">
                                        <p class="mb-2 font-medium">
                                            {{
                                                form.type === 'instruction'
                                                    ? 'Instruction-based Agent (No Training)'
                                                    : 'Knowledge-based Agent (With Training)'
                                            }}
                                        </p>
                                        <p class="text-muted-foreground leading-relaxed">
                                            <span v-if="form.type === 'instruction'">
                                                Your agent works immediately without any training. It uses only the system instructions you provide
                                                and relies on the AI's pre-existing knowledge. This is perfect for general-purpose assistants,
                                                customer support, or when you need quick deployment without uploading documents.
                                            </span>
                                            <span v-else>
                                                Your agent will be trained on your uploaded documents and files. This creates a specialized knowledge
                                                base that allows your agent to provide accurate, contextual answers based on your specific data.
                                                Training takes some time but results in highly specialized expertise for your domain.
                                            </span>
                                        </p>
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
                                                                    :placeholder="
                                    form.type === AgentType.KNOWLEDGE_BASED
                                        ? 'You are a specialized assistant trained on our company knowledge base. Use the uploaded documents to provide accurate and detailed answers...'
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

                    <div v-if="showFileUpload">
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
                                <Switch id="is_public" v-model="form.is_public" />
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
                        <Button :disabled="isSubmitting" size="lg" type="submit">
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
