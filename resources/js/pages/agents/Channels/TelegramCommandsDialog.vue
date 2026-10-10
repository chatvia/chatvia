<script setup lang="ts">
// Editor for the Telegram bot's slash commands. Each command is a saved message
// or a prompt run through the agent. Built-ins (/start, /reset, /help) arrive
// pre-filled and pinned; customs can be added or removed. Same draft → dialog →
// commit-on-Save pattern as the Guardrails editor: opening copies the saved
// commands into a draft, and nothing is stored until Save.
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { commands as telegramCommands } from '@/routes/agents/channels/telegram';
import { useForm } from '@inertiajs/vue3';
import { Check, LoaderCircle, Plus, Trash2, X } from 'lucide-vue-next';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { TelegramCommand } from './types';

const props = defineProps<{
    agentId: string;
    /** The saved commands; copied into the draft each time the dialog opens. */
    commands: TelegramCommand[];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const form = useForm<{ commands: TelegramCommand[] }>({ commands: [] });

// Start every open from the saved commands, with no stale errors. Runs before
// the dialog renders, so the draft is in place on the first frame.
watch(open, (isOpen) => {
    if (!isOpen) return;

    form.clearErrors();
    form.commands = props.commands.map((c) => ({ ...c }));
});

const addCommand = () => form.commands.push({ command: '', description: '', type: 'prompt', value: '', builtin: false });
const removeCommand = (index: number) => form.commands.splice(index, 1);

const save = () => {
    form
        // Drop fully-blank custom rows so a stray empty row never blocks the save;
        // built-ins always go through, and the server validates the rest.
        .transform((data) => ({
            commands: data.commands.filter((c) => c.builtin || c.command.trim() || c.description.trim() || c.value.trim()),
        }))
        .put(telegramCommands.url(props.agentId), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                open.value = false;
                toast.success(t('agents.channels.setup.telegram.commands.saved_toast'));
            },
        });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ t('agents.channels.setup.telegram.commands.dialog_title') }}</DialogTitle>
                <DialogDescription>{{ t('agents.channels.setup.telegram.commands.dialog_description') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-3">
                <div v-for="(command, index) in form.commands" :key="index" class="space-y-2 rounded-lg border p-3">
                    <div class="flex items-center gap-2">
                        <!-- Command name: read-only for built-ins, editable for custom -->
                        <div
                            v-if="command.builtin"
                            class="bg-muted text-foreground/80 flex h-9 w-32 shrink-0 items-center rounded-md px-3 font-mono text-sm"
                        >
                            /{{ command.command }}
                        </div>
                        <div v-else class="relative w-32 shrink-0">
                            <span class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-sm">/</span>
                            <Input
                                v-model="command.command"
                                :placeholder="t('agents.channels.setup.telegram.commands.command_placeholder')"
                                class="ps-6 font-mono"
                            />
                        </div>

                        <!-- Description (shown in Telegram's command menu) -->
                        <Input
                            v-model="command.description"
                            :placeholder="t('agents.channels.setup.telegram.commands.description_placeholder')"
                            class="flex-1"
                        />

                        <!-- Mode: prompt (run through agent) vs message (canned) -->
                        <Select v-model="command.type">
                            <SelectTrigger class="w-36 shrink-0"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="prompt">{{ t('agents.channels.setup.telegram.commands.mode_prompt') }}</SelectItem>
                                <SelectItem value="message">{{ t('agents.channels.setup.telegram.commands.mode_message') }}</SelectItem>
                            </SelectContent>
                        </Select>

                        <Button
                            v-if="!command.builtin"
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:text-destructive shrink-0"
                            :aria-label="t('agents.channels.setup.telegram.commands.remove')"
                            @click="removeCommand(index)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                        <Badge v-else variant="secondary" class="shrink-0">
                            {{ t('agents.channels.setup.telegram.commands.builtin_badge') }}
                        </Badge>
                    </div>

                    <Textarea
                        v-model="command.value"
                        :rows="2"
                        :placeholder="
                            command.type === 'prompt'
                                ? t('agents.channels.setup.telegram.commands.prompt_placeholder')
                                : t('agents.channels.setup.telegram.commands.message_placeholder')
                        "
                    />

                    <p class="text-muted-foreground text-xs">
                        {{
                            command.command === 'reset'
                                ? t('agents.channels.setup.telegram.commands.reset_hint')
                                : command.command === 'help'
                                  ? t('agents.channels.setup.telegram.commands.help_hint')
                                  : t('agents.channels.setup.telegram.commands.name_token_hint')
                        }}
                    </p>
                </div>

                <p v-for="(message, key) in form.errors" :key="key" class="text-destructive text-xs">{{ message }}</p>
            </div>

            <Button type="button" variant="outline" size="sm" class="w-fit" @click="addCommand">
                <Plus class="me-2 size-4" />
                {{ t('agents.channels.setup.telegram.commands.add') }}
            </Button>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="open = false">
                    <X class="me-2 size-4" />
                    {{ t('agents.channels.setup.telegram.commands.cancel') }}
                </Button>
                <Button type="button" :disabled="form.processing" @click="save">
                    <LoaderCircle v-if="form.processing" class="me-2 size-4 animate-spin" />
                    <Check v-else class="me-2 size-4" />
                    {{ t('agents.channels.setup.telegram.commands.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
