<script lang="ts" setup>
/**
 * "Revise answer" dialog of the chat log: shows the visitor's question and the
 * assistant's reply, and saves the operator's expected answer as a Q&A entry
 * so future similar questions use it.
 */
import { reviseAnswer } from '@/api/chatLog';
import { errorMessage } from '@/api/http';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { LoaderCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agentId: string;
    /** The assistant message being revised, or null when nothing is selected. */
    messageId: string | null;
    userMessage: string;
    botResponse: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    saved: [messageId: string, qaId: number];
}>();

const { t } = useI18n();

// Local copies: the textareas are bound with v-model even where they are read-only.
const userMessageText = ref('');
const botResponseText = ref('');
const expectedResponse = ref('');
const isSaving = ref(false);

watch(open, (isOpen) => {
    if (!isOpen) return;
    userMessageText.value = props.userMessage;
    botResponseText.value = props.botResponse;
    expectedResponse.value = '';
});

function close() {
    open.value = false;
    expectedResponse.value = '';
}

async function save() {
    const messageId = props.messageId;
    if (!expectedResponse.value.trim() || !userMessageText.value.trim() || !messageId) return;

    isSaving.value = true;
    try {
        const qaId = await reviseAnswer(props.agentId, {
            message_id: messageId,
            user_message: userMessageText.value,
            expected_response: expectedResponse.value,
        });
        emit('saved', messageId, qaId);
        close();
    } catch (error) {
        toast.error(errorMessage(error, t('agents.chatlog.revise.save_error')));
    } finally {
        isSaving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ t('agents.chatlog.revise.title') }}</DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <Label for="user-message" class="text-sm font-medium">{{ t('agents.chatlog.revise.user_message') }}</Label>
                    <Textarea
                        id="user-message"
                        v-model="userMessageText"
                        :placeholder="t('agents.chatlog.revise.user_message_placeholder')"
                        class="min-h-[100px] resize-none"
                        disabled
                    />
                </div>

                <div class="space-y-2">
                    <Label for="bot-response" class="text-sm font-medium">{{ t('agents.chatlog.revise.bot_response') }}</Label>
                    <Textarea
                        id="bot-response"
                        v-model="botResponseText"
                        :placeholder="t('agents.chatlog.revise.bot_response_placeholder')"
                        class="min-h-[120px] resize-none"
                        disabled
                    />
                </div>

                <div class="space-y-2">
                    <Label for="expected-response" class="text-sm font-medium">{{ t('agents.chatlog.revise.expected_response') }}</Label>
                    <Textarea
                        id="expected-response"
                        v-model="expectedResponse"
                        :placeholder="t('agents.chatlog.revise.expected_response_placeholder')"
                        class="min-h-[150px] resize-none"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="close" :disabled="isSaving"> {{ t('agents.chatlog.revise.cancel') }} </Button>
                <Button @click="save" :disabled="!expectedResponse.trim() || isSaving">
                    <LoaderCircle v-if="isSaving" class="me-2 h-4 w-4 animate-spin" />
                    {{ isSaving ? t('agents.chatlog.revise.saving') : t('agents.chatlog.revise.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
