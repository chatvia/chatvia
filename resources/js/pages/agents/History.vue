<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Clock, LoaderCircle, Save, MessageSquare, Database, Trash2, Download } from 'lucide-vue-next';
import HeaderActions from '@/components/HeaderActions.vue';
import { usePage} from '@inertiajs/vue3';

const page = usePage();

interface Props {
    agent: any
}

interface Settings {
    retention: number;
}

const props = defineProps<Props>();

const settings = page.props.settings as Settings;

const form = useForm({
    retention: settings.retention,
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

// Computed properties for header actions
const hasUnsavedChanges = computed(() => {
    return (
        form.retention !== settings.retention
    );
});

const hasFormErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
});

</script>

<template>
    <AgentLayout :agent="agent" title="Message History">
        <template #header-actions>
            <HeaderActions
                :is-submitting="isSubmitting"
                :has-unsaved-changes="hasUnsavedChanges"
                :show-success-message="form.recentlySuccessful"
                success-message="Settings saved successfully"
                :show-error-message="hasFormErrors"
                error-message="Please fix errors below"
                save-label="Save Settings"
                :save-icon="Save"
                form-id="history-form"
            />
        </template>
        <form id="history-form" @submit.prevent="updateHistorySettings" class="space-y-6">
            <div class="flex justify-center">
                <Card class="w-full max-w-6xl">
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
            </div>

        </form>
    </AgentLayout>
</template>
