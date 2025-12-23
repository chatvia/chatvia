<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import axios from 'axios';
import { Loader2, RotateCcw, Save } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    agentId: number | string;
    initialInstructions?: string | null;
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

    general: `You are a helpful AI assistant. Your goal is to provide accurate, helpful, and friendly responses to user inquiries.

Be conversational and approachable while maintaining professionalism. If you're unsure about something, be honest about it and offer to help find the information.

Always:
- Be polite and respectful
- Provide clear and concise answers
- Ask clarifying questions when needed
- Admit when you don't know something`,

    support: `You are a customer support agent. Your primary goal is to help customers resolve their issues quickly and efficiently while maintaining a friendly and professional tone.

Guidelines:
- Always greet the customer warmly
- Listen carefully to understand their problem
- Provide step-by-step solutions when possible
- Offer alternatives if the first solution doesn't work
- Apologize sincerely for any inconvenience
- Follow up to ensure the issue is resolved
- End conversations on a positive note

If you cannot resolve an issue, offer to escalate to a human agent or provide contact information for further assistance.`,

    sales: `You are a knowledgeable sales assistant. Your goal is to help customers find the right products or services that meet their needs.

Guidelines:
- Understand customer needs by asking relevant questions
- Highlight product benefits that match their requirements
- Provide honest and accurate product information
- Address objections professionally
- Suggest complementary products when appropriate
- Never be pushy or aggressive
- Respect the customer's decision

Focus on building trust and providing value rather than just making a sale.`,
};

// State
const selectedPreset = ref<string>('base');
const instructions = ref<string>(props.initialInstructions || presets.base);
const originalInstructions = ref<string>(props.initialInstructions || presets.base);
const isSaving = ref(false);

// Computed
const hasChanges = computed(() => {
    return instructions.value !== originalInstructions.value;
});

// Watch for preset changes
watch(selectedPreset, (newPreset) => {
    if (newPreset !== 'custom') {
        instructions.value = presets[newPreset as keyof typeof presets] || presets.base;
    }
});

// Detect which preset matches current instructions
const detectPreset = (text: string): string => {
    for (const [key, value] of Object.entries(presets)) {
        if (value.trim() === text.trim()) {
            return key;
        }
    }
    return 'custom';
};

// Initialize
onMounted(() => {
    if (props.initialInstructions) {
        instructions.value = props.initialInstructions;
        originalInstructions.value = props.initialInstructions;
        selectedPreset.value = detectPreset(props.initialInstructions);
    }
});

// Reset to original
const resetInstructions = () => {
    instructions.value = originalInstructions.value;
    selectedPreset.value = detectPreset(originalInstructions.value);
};

// Save instructions
const saveInstructions = async () => {
    isSaving.value = true;

    try {
        await axios.patch(route('agents.update', { agent: props.agentId }), {
            system_instructions: instructions.value,
        });

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

        <!-- Preset Selector -->
        <div class="flex items-center gap-2">
            <Select v-model="selectedPreset" class="flex-1">
                <SelectTrigger>
                    <SelectValue placeholder="Select a preset..." />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        <SelectLabel class="text-muted-foreground text-xs font-normal">Base Instructions</SelectLabel>
                        <SelectItem value="base">Base Instructions</SelectItem>
                    </SelectGroup>
                    <SelectGroup>
                        <SelectLabel class="text-muted-foreground text-xs font-normal">Examples</SelectLabel>
                        <SelectItem value="general">General AI agent</SelectItem>
                        <SelectItem value="support">Customer support agent</SelectItem>
                        <SelectItem value="sales">Sales agent</SelectItem>
                    </SelectGroup>
                    <SelectGroup>
                        <SelectLabel class="text-muted-foreground text-xs font-normal">Custom prompt</SelectLabel>
                        <SelectItem value="custom">Custom prompt</SelectItem>
                    </SelectGroup>
                </SelectContent>
            </Select>
            <Button type="button" variant="outline" size="icon" @click="resetInstructions" :disabled="!hasChanges" title="Reset to saved">
                <RotateCcw class="h-4 w-4" />
            </Button>
        </div>

        <!-- Instructions Textarea -->
        <Textarea
            v-model="instructions"
            @input="onInstructionsInput"
            placeholder="Enter your custom instructions..."
            class="min-h-[200px] font-mono text-sm"
            rows="10"
        />

        <!-- Save Button -->
        <div class="border-t pt-3">
            <Button @click="saveInstructions" :disabled="!hasChanges || isSaving" size="sm">
                <Loader2 v-if="isSaving" class="mr-2 h-4 w-4 animate-spin" />
                <Save v-else class="mr-2 h-4 w-4" />
                Save
            </Button>
        </div>
    </div>
</template>
