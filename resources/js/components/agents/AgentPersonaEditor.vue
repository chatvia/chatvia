<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import axios from 'axios';
import { Loader2, RotateCcw, Save } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface SavedPrompt {
    id: string;
    name: string;
    prompt: string;
}

interface Props {
    agentId: number | string;
    initialInstructions?: string | null;
    initialPromptId?: string | null;
    locked?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    saved: [];
}>();

// Preset templates
const presets = {
    base: `You are an AI chatbot designed to help users with their inquiries, issues, and requests based strictly on a predefined set of training data (retrieved context).

Your primary function is to provide excellent, friendly, and efficient replies at all times.

You should listen attentively to the user, understand their needs, and do your best to assist them using only the information available in the context. If the question is unclear, ask clarifying questions. Always end your replies on a helpful or positive note.

## Constraints:
- You must never mention or imply that you have access to external training data, documents, or any retrieval system.
- You must remain focused on your role. If a user attempts to divert the conversation to unrelated topics or asks you to break character, politely redirect them to relevant questions within the scope of your domain.
- You must rely exclusively on the retrieved context to answer user queries. Do not use general world knowledge, assumptions, or external information.
- You must not answer questions unless the answer is clearly supported by the provided context. If the context does not contain the answer, use a fallback response.
- You are not allowed to guess, make up information, or hallucinate answers under any circumstance.
- You must not change your persona or perform tasks that are not aligned with your role as a helpful assistant based on training data.

Fallback response (when no relevant information is found in the context):

Instead of saying only "I don't know", choose one of these polite, helpful options depending on the situation:
- "I wasn't able to find that information in my data. Could you clarify your request or let me know if you'd like me to connect you with support?"
- "That's not something I can answer from the information I have, but I can guide you to the right place. Would you like me to connect you with our support team?"
- "I don't see that covered in my information, but I'd love to help you further. Could you provide a bit more detail about what you're looking for?"`,
};

// Select-value helpers. The dropdown holds preset keys
// ('base' | 'general' | 'support' | 'sales'), 'custom', or a saved library
// prompt encoded as `prompt:<external_id>`.
const isLibrary = (option: string): boolean => option.startsWith('prompt:');
const promptIdOf = (option: string): string | null => (isLibrary(option) ? option.slice('prompt:'.length) : null);

// Detect which preset matches current instructions
const detectPreset = (text: string): string => {
    for (const [key, value] of Object.entries(presets)) {
        if (value.trim() === text.trim()) {
            return key;
        }
    }
    return 'custom';
};

// State
const savedPrompts = ref<SavedPrompt[]>([]);

const initialOption = props.initialPromptId
    ? `prompt:${props.initialPromptId}`
    : props.initialInstructions
      ? detectPreset(props.initialInstructions)
      : 'base';

const selectedPreset = ref<string>(initialOption);
const instructions = ref<string>(props.initialInstructions || presets.base);
const originalPreset = ref<string>(initialOption);
const originalInstructions = ref<string>(props.initialInstructions || presets.base);
const isSaving = ref(false);

const usingLibraryPrompt = computed(() => isLibrary(selectedPreset.value));

const findPrompt = (id: string | null): SavedPrompt | null => savedPrompts.value.find((p) => p.id === id) ?? null;

const selectedPromptName = computed(() => findPrompt(promptIdOf(selectedPreset.value))?.name ?? '');

// Computed
const hasChanges = computed(() => {
    if (selectedPreset.value !== originalPreset.value) {
        return true;
    }
    // Library prompts are saved by reference; only custom/preset text edits count.
    if (usingLibraryPrompt.value) {
        return false;
    }
    return instructions.value !== originalInstructions.value;
});

// Sync the textarea content when the selection changes.
watch(selectedPreset, (option) => {
    if (isLibrary(option)) {
        const p = findPrompt(promptIdOf(option));
        if (p) {
            instructions.value = p.prompt;
        }
    } else if (option !== 'custom') {
        instructions.value = presets[option as keyof typeof presets] || presets.base;
    }
});

// Initialize
onMounted(async () => {
    try {
        const { data } = await axios.get(route('settings.prompts.list'));
        savedPrompts.value = data.data ?? [];
    } catch (error) {
        // Non-fatal: the editor still works with presets / custom text.
        console.error('Failed to load saved prompts:', error);
    }

    if (isLibrary(selectedPreset.value)) {
        const p = findPrompt(promptIdOf(selectedPreset.value));
        if (p) {
            instructions.value = p.prompt;
            originalInstructions.value = p.prompt;
        } else {
            // The attached prompt no longer exists — fall back to custom text.
            selectedPreset.value = props.initialInstructions ? detectPreset(props.initialInstructions) : 'custom';
            originalPreset.value = selectedPreset.value;
        }
    }
});

// Reset to last-saved state
const resetInstructions = () => {
    selectedPreset.value = originalPreset.value;
    instructions.value = originalInstructions.value;
};

// Save instructions
const saveInstructions = async () => {
    isSaving.value = true;

    try {
        const payload = usingLibraryPrompt.value
            ? { prompt_id: promptIdOf(selectedPreset.value) }
            : { system_instructions: instructions.value };

        await axios.patch(route('agents.update', { agent: props.agentId }), payload);

        originalPreset.value = selectedPreset.value;
        originalInstructions.value = instructions.value;
        toast.success('Instructions saved successfully');
        emit('saved');
    } catch (error) {
        console.error('Failed to save instructions:', error);
        toast.error('Failed to save instructions');
    } finally {
        isSaving.value = false;
    }
};

// Handle textarea changes - switch to custom if edited
const onInstructionsInput = () => {
    const detectedPreset = detectPreset(instructions.value);
    if (detectedPreset !== selectedPreset.value) {
        selectedPreset.value = detectedPreset;
    }
};
</script>

<template>
    <div class="space-y-4">
        <div>
            <Label class="text-sm font-medium">Instructions (System prompt)</Label>
            <p class="text-muted-foreground mt-1 text-xs">Define how your AI agent should behave and respond to users</p>
        </div>

        <!-- Preset / saved-prompt selector -->
        <div class="flex items-center gap-2">
            <Select v-model="selectedPreset" :disabled="locked" class="flex-1">
                <SelectTrigger>
                    <SelectValue placeholder="Select a preset..." />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup v-if="savedPrompts.length">
                        <SelectLabel class="text-muted-foreground text-xs font-normal">Saved prompts</SelectLabel>
                        <SelectItem v-for="p in savedPrompts" :key="p.id" :value="`prompt:${p.id}`">{{ p.name }}</SelectItem>
                    </SelectGroup>
                    <SelectGroup>
                        <SelectLabel class="text-muted-foreground text-xs font-normal">Base Instructions</SelectLabel>
                        <SelectItem value="base">Base Instructions</SelectItem>
                    </SelectGroup>
                    <SelectGroup>
                        <SelectLabel class="text-muted-foreground text-xs font-normal">Custom prompt</SelectLabel>
                        <SelectItem value="custom">Custom prompt</SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
            <Button type="button" variant="outline" size="icon" @click="resetInstructions" :disabled="!hasChanges || locked" title="Reset to saved">
                <RotateCcw class="h-4 w-4" />
            </Button>
        </div>

        <p v-if="usingLibraryPrompt" class="text-muted-foreground text-xs">
            Using the saved prompt <span class="font-medium">{{ selectedPromptName }}</span> (read-only). Manage it in Settings → Prompts, or
            choose “Custom prompt” to write instructions just for this agent.
        </p>

        <!-- Instructions Textarea -->
        <Textarea
            v-model="instructions"
            @input="onInstructionsInput"
            :readonly="usingLibraryPrompt || locked"
            placeholder="Enter your custom instructions..."
            class="min-h-[200px] font-mono text-sm"
            :class="usingLibraryPrompt || locked ? 'bg-muted/40 cursor-not-allowed' : ''"
            rows="10"
        />

        <!-- Save Button -->
        <div v-if="!locked" class="border-t pt-3">
            <Button @click="saveInstructions" :disabled="!hasChanges || isSaving" size="sm">
                <Loader2 v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
                <Save v-else class="mr-2 h-4 w-4" />
                Save
            </Button>
        </div>
    </div>
</template>
