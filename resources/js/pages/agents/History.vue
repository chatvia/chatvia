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

const props = defineProps<Props>();

const form = useForm({
    enabled: page.props.settings.enabled,
    retention:  page.props.settings.retention,
    context_window_enabled: page.props.settings.context_window_enabled,
    context_window_size: page.props.settings.context_window_size,
});

const isSubmitting = ref(false);

const updateHistorySettings = () => {
    isSubmitting.value = true;

    form.patch(route('agents.settings.update', {agent: props.agent.id, group: 'message_history'}), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const contextWindowOptions = [
    { value: 0, label: 'Disabled' },
    { value: 10, label: '10 messages' },
    { value: 25, label: '25 messages' },
    { value: 50, label: '50 messages' },
    { value: -1, label: 'All messages' },
];

const retentionOptions = [
    { value: 7, label: '7 days' },
    { value: 30, label: '30 days' },
    { value: 90, label: '90 days' },
    { value: 180, label: '6 months' },
    { value: 365, label: '1 year' },
    { value: -1, label: 'Forever' },
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
                    <!-- Enable History -->
                    <div class="flex items-center justify-between space-x-2">
                        <div>
                            <Label class="text-base" for="enable_history">Enable message history</Label>
                            <p class="text-sm text-muted-foreground">
                                Store conversation history for this agent
                            </p>
                        </div>
                        <Switch id="enable_history" v-model="form.enabled" />
                    </div>

                    <!-- Retention Period -->
                    <div class="space-y-2">
                        <Label for="retention_days">Message retention period</Label>
                        <Select v-model="form.retention" :disabled="!form.enabled">
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
                        Configure how much conversation history the agent remembers during conversations
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Enable Context -->
                    <div class="flex items-center justify-between space-x-2">
                        <div>
                            <Label class="text-base" for="enable_context">Enable context window</Label>
                            <p class="text-sm text-muted-foreground">
                                Allow the agent to reference previous messages in the conversation
                            </p>
                        </div>
                        <Switch id="enable_context" v-model="form.context_window_enabled" :disabled="!form.enabled" />
                    </div>

                    <!-- Context Window Size -->
                    <div class="space-y-2">
                        <Label for="context_window_size">Context window size</Label>
                        <Select v-model="form.context_window_size" :disabled="!form.context_window_enabled || !form.enabled">
                            <SelectTrigger>
                                <SelectValue placeholder="Select context window size" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in contextWindowOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-sm text-muted-foreground">
                            Number of previous messages the agent can access during conversations. Larger windows may increase response time.
                        </p>

                        <div class="mt-2 flex items-start gap-2 rounded-md bg-amber-50 p-3 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400">
                            <div class="mt-0.5 shrink-0">
                                <AlertCircle class="h-4 w-4" />
                            </div>
                            <p class="text-sm">
                                Consider disabling the context window for agents designed for content creation based on keywords or other stateless tasks. This can improve response quality for tasks that don't require conversation history.
                            </p>
                        </div>
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
