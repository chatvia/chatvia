<script setup lang="ts">
/**
 * The persona editor of the Setup page: the agent's system instructions,
 * either written inline (starting from the base preset) or taken read-only
 * from a saved library prompt. Saves itself via agents.update and emits
 * `saved` so the page can reload its preview.
 */
import { fetchLibraryPrompts, updateAgentInstructions, type LibraryPrompt } from '@/api/agentPersona';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Loader2, RotateCcw, Save } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

interface Props {
    agentId: string;
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
    base: `## Role
You are a friendly, helpful AI assistant. Answer user queries using only the retrieved context below.

### Rules

- Use only the context. Never use outside knowledge, assumptions, or made-up info.
- Never mention the context, training data, documents, or any retrieval system.
- Stay in your role. If asked to break character or go off-topic, politely redirect to relevant questions.
- If the context doesn't clearly support an answer, use a fallback (don't guess).
- If a query is unclear, ask a clarifying question.
- Be warm and concise. End on a helpful or positive note.

### Fallback

When the context lacks the answer, vary naturally:

- "I couldn't find that in my information. Could you clarify, or shall I connect you with support?"
- "I can't answer that from what I have, but I can point you the right way—want me to connect you with our support team?"
- "I don't see that covered, but I'd love to help. Could you share a bit more detail?"
    `,
};

// Select-value helpers. The dropdown holds 'base' (the editable default
// instructions) or a saved library prompt encoded as `prompt:<external_id>`.
// There's no separate "custom" option — picking Base Instructions and
// editing the textarea is the custom path.
const isLibrary = (option: string): boolean => option.startsWith('prompt:');
const promptIdOf = (option: string): string | null => (isLibrary(option) ? option.slice('prompt:'.length) : null);

// State
const savedPrompts = ref<LibraryPrompt[]>([]);

const initialOption = props.initialPromptId ? `prompt:${props.initialPromptId}` : 'base';

const selectedPreset = ref<string>(initialOption);
const instructions = ref<string>(props.initialInstructions || presets.base);
const originalPreset = ref<string>(initialOption);
const originalInstructions = ref<string>(props.initialInstructions || presets.base);
const isSaving = ref(false);

const usingLibraryPrompt = computed(() => isLibrary(selectedPreset.value));

const findPrompt = (id: string | null): LibraryPrompt | null => savedPrompts.value.find((p) => p.id === id) ?? null;

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

// Sync the textarea content when the selection changes. Switching to a
// library prompt replaces the textarea with that prompt's text (read-only);
// switching back to Base Instructions restores the saved-for-this-agent text
// (or the default preset if the agent has never had custom instructions).
watch(selectedPreset, (option) => {
    if (isLibrary(option)) {
        const p = findPrompt(promptIdOf(option));
        if (p) {
            instructions.value = p.prompt;
        }
    } else {
        instructions.value = props.initialInstructions || presets.base;
    }
});

// Initialize
onMounted(async () => {
    try {
        savedPrompts.value = await fetchLibraryPrompts();
    } catch {
        // Non-fatal (e.g. no access to the prompt library): the editor still
        // works with the base instructions.
    }

    if (isLibrary(selectedPreset.value)) {
        const p = findPrompt(promptIdOf(selectedPreset.value));
        if (p) {
            instructions.value = p.prompt;
            originalInstructions.value = p.prompt;
        } else {
            // The attached prompt no longer exists — fall back to Base Instructions.
            selectedPreset.value = 'base';
            originalPreset.value = 'base';
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
        await updateAgentInstructions(
            props.agentId,
            usingLibraryPrompt.value ? { prompt_id: promptIdOf(selectedPreset.value) } : { system_instructions: instructions.value },
        );

        originalPreset.value = selectedPreset.value;
        originalInstructions.value = instructions.value;
        toast.success(t('shared.persona_editor.save_success'));
        emit('saved');
    } catch {
        toast.error(t('shared.persona_editor.save_error'));
    } finally {
        isSaving.value = false;
    }
};
</script>

<template>
    <div class="space-y-4">
        <div>
            <Label class="text-sm font-medium">{{ t('shared.persona_editor.label') }}</Label>
            <p class="text-muted-foreground mt-1 text-xs">{{ t('shared.persona_editor.description') }}</p>
        </div>

        <!-- Preset / saved-prompt selector -->
        <div class="flex items-center gap-2">
            <Select v-model="selectedPreset" :disabled="locked" class="flex-1">
                <SelectTrigger>
                    <SelectValue :placeholder="t('shared.persona_editor.select_placeholder')" />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup v-if="savedPrompts.length">
                        <SelectLabel class="text-muted-foreground text-xs font-normal">{{ t('shared.persona_editor.saved_prompts') }}</SelectLabel>
                        <SelectItem v-for="p in savedPrompts" :key="p.id" :value="`prompt:${p.id}`">{{ p.name }}</SelectItem>
                    </SelectGroup>
                    <SelectGroup>
                        <SelectLabel class="text-muted-foreground text-xs font-normal">{{ t('shared.persona_editor.default_group') }}</SelectLabel>
                        <SelectItem value="base">{{ t('shared.persona_editor.base_instructions') }}</SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
            <Button
                type="button"
                variant="outline"
                size="icon"
                @click="resetInstructions"
                :disabled="!hasChanges || locked"
                :title="t('shared.persona_editor.reset_title')"
                :aria-label="t('shared.persona_editor.reset_title')"
            >
                <RotateCcw class="h-4 w-4" />
            </Button>
        </div>

        <p v-if="usingLibraryPrompt" class="text-muted-foreground text-xs">
            {{
                t('shared.persona_editor.library_hint', {
                    name: selectedPromptName,
                    base: t('shared.persona_editor.base_instructions'),
                })
            }}
        </p>

        <!-- Instructions Textarea — editable when Base Instructions is active. -->
        <Textarea
            v-model="instructions"
            :readonly="usingLibraryPrompt || locked"
            :placeholder="t('shared.persona_editor.textarea_placeholder')"
            class="field-sizing-fixed h-[400px] resize-y overflow-y-auto font-mono text-sm"
            :class="usingLibraryPrompt || locked ? 'bg-muted/40 cursor-not-allowed' : ''"
            rows="10"
        />

        <!-- Save Button -->
        <div v-if="!locked" class="border-t pt-3">
            <Button @click="saveInstructions" :disabled="!hasChanges || isSaving" size="sm">
                <Loader2 v-if="isSaving" class="me-2 h-4 w-4 animate-spin" />
                <Save v-else class="me-2 h-4 w-4" />
                {{ t('shared.persona_editor.save') }}
            </Button>
        </div>
    </div>
</template>
