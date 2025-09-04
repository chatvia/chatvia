<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Lock, Save } from 'lucide-vue-next';
import HeaderActions from '@/components/HeaderActions.vue';

interface Props {
    agent: any
}

const props = defineProps<Props>();

// Check if agent is widget type
const isWidgetAgent = computed(() => {
    return props.agent.type === 'widget';
});

const form = useForm({
    is_active: props.agent.is_active,
    is_public: props.agent.is_public,
});

const isSubmitting = ref(false);

const updateSecuritySettings = () => {
    isSubmitting.value = true;
    form.patch(route('agents.security.update', props.agent.id), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

// Computed properties for header actions
const hasUnsavedChanges = computed(() => {
    return (
        form.is_active !== props.agent.is_active ||
        form.is_public !== props.agent.is_public
    );
});

const hasFormErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
});
</script>

<template>
    <AgentLayout :agent="agent" title="Security">
        <template #header-actions>
            <HeaderActions
                :is-submitting="isSubmitting"
                :has-unsaved-changes="hasUnsavedChanges"
                :show-success-message="form.recentlySuccessful"
                success-message="Security settings saved"
                :show-error-message="hasFormErrors"
                error-message="Please fix errors below"
                save-label="Save Changes"
                :save-icon="Save"
                form-id="security-form"
            />
        </template>

        <form @submit.prevent="updateSecuritySettings" id="security-form" class="space-y-6">
            <div class="flex justify-center">
                <!-- Access Control (hidden for widget agents) -->
                <Card v-if="!isWidgetAgent" class="w-full max-w-6xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Lock class="h-5 w-5" />
                        Access Control
                    </CardTitle>
                    <CardDescription>
                        Control who can access your agent and how
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Accessible Toggle -->
                        <div class="flex items-center justify-between space-x-2">
                            <div>
                                <Label class="text-base" for="is_active">Accessible</Label>
                                <p class="text-sm text-muted-foreground">
                                    When enabled, users can access and interact with this agent
                                </p>
                            </div>
                            <Switch id="is_active" v-model="form.is_active" />
                        </div>

                        <!-- Public Access Toggle -->
                        <div class="flex items-center justify-between space-x-2">
                            <div>
                                <Label class="text-base" for="is_public">Make this agent public</Label>
                                <p class="text-sm text-muted-foreground">
                                    Public agents can be accessed by anyone with the link
                                </p>
                            </div>
                            <Switch id="is_public" v-model="form.is_public" />
                        </div>
                    </div>
                </CardContent>
            </Card>
            </div>
        </form>
    </AgentLayout>
</template>
