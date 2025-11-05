<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Lock } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface Props {
    agent: any
}

const props = defineProps<Props>();

// Check if agent is widget type
const isWidgetAgent = computed(() => {
    return props.agent.type === 'widget';
});

const form = useForm({
    is_public: props.agent.is_public,
});

const isSubmitting = ref(false);

const updateSecuritySettings = () => {
    isSubmitting.value = true;
    form.patch(route('agents.security.update', props.agent.id), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            toast.success('Security settings updated');
        },
        onError: () => {
            isSubmitting.value = false;
            toast.error('Failed to update security settings');
        }
    });
};

// Auto-save when toggle changes
watch(() => form.is_public, (newValue, oldValue) => {
    // Only save if the value actually changed and it's not the initial load
    if (oldValue !== undefined && newValue !== oldValue) {
        updateSecuritySettings();
    }
});
</script>

<template>
    <AgentLayout :agent="agent" title="Security">
        <div class="space-y-6">
            <div class="flex justify-center">
                <Card v-if="!isWidgetAgent" class="w-full max-w-6xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Lock class="h-5 w-5" />
                        Access Control
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Public Access Toggle -->
                    <div class="flex items-center justify-between space-x-2">
                        <div class="flex-1">
                            <Label class="text-base" for="is_public">Make this agent public</Label>
                            <p class="text-sm text-muted-foreground mt-1">
                                Public agents can be accessed by anyone with the link
                            </p>
                        </div>
                        <Switch 
                            id="is_public" 
                            v-model="form.is_public"
                            :disabled="isSubmitting"
                        />
                    </div>
                </CardContent>
            </Card>
            </div>
        </div>
    </AgentLayout>
</template>
