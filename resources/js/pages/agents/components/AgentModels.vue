<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Model {
    id: string;
    name: string;
    provider: string;
    description: string;
    context_length: number;
    max_tokens: number;
    pricing: {
        input: number;
        output: number;
    };
}

interface Props {
    agent: {
        id: number;
        external_id: string;
        name: string;
        models: string[];
    };
    availableModels: Model[];
}

const props = defineProps<Props>();

const form = useForm({
    models: props.agent.models || [],
});

const isLoading = ref(false);
const error = ref<string | null>(null);
const success = ref<string | null>(null);

const handleSubmit = () => {
    isLoading.value = true;
    error.value = null;
    success.value = null;

    form.put(route('agents.update-models', props.agent.external_id), {
        onSuccess: () => {
            success.value = 'Models updated successfully';
            isLoading.value = false;
        },
        onError: (errors) => {
            error.value = errors.message || 'Failed to update models';
            isLoading.value = false;
        },
    });
};

const toggleModel = (modelId: string) => {
    const index = form.models.indexOf(modelId);
    if (index === -1) {
        form.models.push(modelId);
    } else {
        form.models.splice(index, 1);
    }
};

const _getModelDetails = (modelId: string) => {
    return props.availableModels.find((model) => model.id === modelId);
};
</script>

<template>
    <div class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Agent Models</CardTitle>
                <CardDescription>
                    Select the AI models that your agent can use. You can choose multiple models, and your agent will automatically select the most
                    appropriate one based on the context and requirements.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <!-- Alert Messages -->
                <Alert v-if="error" variant="destructive" class="mb-4">
                    <AlertDescription>{{ error }}</AlertDescription>
                </Alert>
                <Alert v-if="success" variant="default" class="mb-4">
                    <AlertDescription>{{ success }}</AlertDescription>
                </Alert>

                <!-- Models List -->
                <div class="space-y-4">
                    <div v-for="model in availableModels" :key="model.id" class="flex items-start space-x-4 rounded-lg border p-4">
                        <Checkbox
                            :id="model.id"
                            :checked="form.models.includes(model.id)"
                            @update:checked="() => toggleModel(model.id)"
                            class="mt-1"
                        />
                        <div class="flex-1 space-y-1">
                            <Label :for="model.id" class="text-base font-medium">
                                {{ model.name }}
                                <span class="text-muted-foreground ml-2 text-sm">({{ model.provider }})</span>
                            </Label>
                            <p class="text-muted-foreground text-sm">{{ model.description }}</p>
                            <div class="text-muted-foreground flex gap-4 text-sm">
                                <span>Context: {{ model.context_length.toLocaleString() }} tokens</span>
                                <span>Max Output: {{ model.max_tokens.toLocaleString() }} tokens</span>
                                <span>Pricing: ${{ model.pricing.input }}/1K input, ${{ model.pricing.output }}/1K output</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mt-6 flex justify-end">
                    <Button @click="handleSubmit" :disabled="isLoading || !form.isDirty" class="gap-2">
                        <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin" />
                        {{ isLoading ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
