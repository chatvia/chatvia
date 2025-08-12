<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Clock, LoaderCircle, Save, MessageSquare, Database, Trash2, Download } from 'lucide-vue-next';
import FormSuccessMessage from '@/components/FormSuccessMessage.vue';
import { usePage} from '@inertiajs/vue3';

const page = usePage();

interface Props {
    agent: any
}

interface Settings {
    retention: number;
    context_window_enabled: boolean;
}

const props = defineProps<Props>();

const settings = page.props.settings as Settings;

const form = useForm({
    retention: settings.retention,
    context_window_enabled: settings.context_window_enabled,
});

const isSubmitting = ref(false);

const updateHistorySettings = () => {
    isSubmitting.value = true;

    form.post(route('agents.settings.update', {agent: props.agent.id, group: 'message_history'}), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const retentionOptions = [
    { value: 7, label: '7 days' },
    { value: 30, label: '30 days' },
    { value: 90, label: '90 days' },
];

</script>

<template>
    <AgentLayout :agent="agent" title="Message History">
        <form @submit.prevent="updateHistorySettings" class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5" />
                        Message History
                    </CardTitle>
                    <CardDescription>
                        Control how your agent handles and stores conversation history
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Retention Period -->
                    <div class="space-y-2">
                        <Label for="retention_days">Message retention period</Label>
                        <Select v-model="form.retention">
                            <SelectTrigger>
                                <SelectValue placeholder="Select retention period" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in retentionOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-sm text-muted-foreground">
                            How long to keep message history before automatic deletion
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Context Window Settings -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        Context Window
                    </CardTitle>
                    <CardDescription>
                        Allow the agent to reference previous messages in the conversation
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Enable Context -->
                    <div class="flex items-center justify-between space-x-2">
                        <div>
                            <Label class="text-base" for="enable_context">Enable context window</Label>
                            <p class="text-sm text-muted-foreground">
                                Disabling the context window will disable reference to previous messages in the conversation.
                            </p>
                        </div>
                        <Switch id="enable_context" v-model="form.context_window_enabled" />
                    </div>


                </CardContent>
            </Card>

            <!-- Save Button -->
            <div class="flex justify-end">
                <FormSuccessMessage
                    :show="form.recentlySuccessful"
                    message="Settings saved successfully"
                />
                <Button :disabled="isSubmitting" size="lg" type="submit">
                    <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                    <Save v-else class="mr-2 h-4 w-4" />
                    Save Settings
                </Button>
            </div>
        </form>
    </AgentLayout>
</template>
