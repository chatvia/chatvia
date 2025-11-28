<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Clock, LoaderCircle } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

const page = usePage();

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData
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

const retentionOptions = [
    { value: 7, label: '7 days' },
    { value: 30, label: '30 days' },
    { value: 90, label: '90 days' },
];

// Watch for changes and auto-save
watch(() => form.retention, (newValue, oldValue) => {
    if (newValue !== oldValue && oldValue !== undefined) {
        isSubmitting.value = true;

        form.post(route('agents.settings.update', {agent: props.agent.id, group: 'message_history'}), {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting.value = false;
                toast.success('Message retention period updated');
            },
            onError: () => {
                isSubmitting.value = false;
                toast.error('Failed to update settings');
            }
        });
    }
});

</script>

<template>
    <AgentLayout
        :agent="agent"
        title="Message History"
        description="Control how your agent handles and stores conversation history"
    >
        <template #header-icon>
            <Clock class="h-5 w-5" />
        </template>

        <div class="flex justify-center">
            <Card class="w-full max-w-6xl">
                <CardContent class="p-6">
                    <div class="space-y-2">
                        <Label for="retention_days" class="flex items-center gap-2">
                            Message retention period
                        </Label>
                        <Select v-model="form.retention" :disabled="isSubmitting">
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
                            How long to keep message history before automatic deletion.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AgentLayout>
</template>
