<script setup lang="ts">
import { ref } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Database, FileText, Upload, X, LoaderCircle } from 'lucide-vue-next';
import type { InertiaForm } from '@inertiajs/vue3';

export interface FormData {
    name: string;
    subdomain: string;
    description: string;
    category: string;
    language: string;
    timezone: string;
    files: File[];
    knowledgeBase: string;
    maxResponseLength: string;
    temperature: number;
    contextWindow: string;
    fallbackBehavior: string;
    errorHandling: string;
    errors: Record<string, string>;
    [key: string]: any; // Add index signature for string keys
}

interface Props {
    form: InertiaForm<FormData>
}

const props = defineProps<Props>();

// States
const uploadedFiles = ref<File[]>([]);
const fileInputRef = ref<HTMLInputElement | null>(null);
const isSubmitting = ref(false);

// File upload handlers
const triggerFileInput = () => {
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
};

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = target.files;
    if (!files?.length) return;

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        uploadedFiles.value.push(file);
        props.form.files.push(file);
    }

    // Reset the input to allow selecting the same file again
    target.value = '';
};

const removeFile = (index: number) => {
    uploadedFiles.value.splice(index, 1);
    props.form.files.splice(index, 1);
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Database class="h-5 w-5" />
                Knowledge Base
            </CardTitle>
            <CardDescription>
                Upload documents, PDFs, or text files to create an agent with specific knowledge
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="grid gap-6">
                <!-- Basic Information -->
                <div class="space-y-4">
                    <div>
                        <Label for="name" required>Agent Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="e.g., Customer Support Assistant"
                            :error="form.errors.name"
                        />
                        <p v-if="form.errors.name" class="text-destructive text-sm mt-1">
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <Label for="subdomain" required>Subdomain</Label>
                        <Input
                            id="subdomain"
                            v-model="form.subdomain"
                            placeholder="e.g., support"
                            :error="form.errors.subdomain"
                        />
                        <p v-if="form.errors.subdomain" class="text-destructive text-sm mt-1">
                            {{ form.errors.subdomain }}
                        </p>
                        <p class="text-muted-foreground text-sm mt-1">
                            This will be used as your agent's URL: support.chatvia.ai
                        </p>
                    </div>

                    <div>
                        <Label for="description">Description</Label>
                        <Input
                            id="description"
                            v-model="form.description"
                            placeholder="Describe what your agent does"
                            :error="form.errors.description"
                        />
                        <p v-if="form.errors.description" class="text-destructive text-sm mt-1">
                            {{ form.errors.description }}
                        </p>
                    </div>
                </div>

                <!-- Upload files -->
                <div>
                    <h3 class="text-lg font-medium mb-3">Upload Knowledge Files</h3>
                    <input
                        ref="fileInputRef"
                        accept=".pdf,.doc,.docx,.txt,.md"
                        class="hidden"
                        multiple
                        type="file"
                        @change="handleFileUpload"
                    />

                    <div
                        class="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-neutral-300 p-8 text-center hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800/50"
                        @click="triggerFileInput"
                    >
                        <Upload class="mb-3 h-8 w-8 text-neutral-400" />
                        <div class="mb-1 text-base font-medium">Upload files</div>
                        <p class="text-sm text-muted-foreground">
                            Drag and drop or click to upload PDF, Word, Text, or Markdown files
                        </p>
                    </div>
                </div>

                <!-- Uploaded files list -->
                <div v-if="uploadedFiles.length > 0" class="space-y-2">
                    <div class="text-sm font-medium">Files to Upload</div>
                    <div class="rounded-md border border-neutral-200 dark:border-neutral-800">
                        <div
                            v-for="(file, index) in uploadedFiles"
                            :key="index"
                            class="flex items-center justify-between border-b border-neutral-200 p-3 last:border-0 dark:border-neutral-800"
                        >
                            <div class="flex items-center gap-3">
                                <FileText class="h-5 w-5 text-neutral-400" />
                                <div>
                                    <div class="font-medium">{{ file.name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ (file.size / 1024).toFixed(1) }} KB
                                    </div>
                                </div>
                            </div>
                            <Button
                                class="h-8 w-8 p-0"
                                size="sm"
                                variant="ghost"
                                @click="removeFile(index)"
                            >
                                <span class="sr-only">Remove file</span>
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template> 