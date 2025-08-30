<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Lock, LoaderCircle, Save } from 'lucide-vue-next';
import FormSuccessMessage from '@/components/FormSuccessMessage.vue';

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
    enable_widget: props.agent.enable_widget ?? true,
    enable_iframe_embedding: props.agent.enable_iframe_embedding ?? true,
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
</script>

<template>
    <AgentLayout :agent="agent" title="Security">
        <form @submit.prevent="updateSecuritySettings" class="space-y-6">
            <!-- Access Control (hidden for widget agents) -->
            <Card v-if="!isWidgetAgent">
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

            <!-- Embedding Settings -->
            <Card>
                <CardHeader>
                    <CardTitle>Embedding Settings</CardTitle>
                    <CardDescription>
                        Control how your agent can be embedded and integrated
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-6">
                        <!-- Enable Widget -->
                        <div class="flex items-center justify-between space-x-2">
                            <div>
                                <Label class="text-base" for="enable_widget">Enable Widget</Label>
                                <p class="text-sm text-muted-foreground">
                                    Allow this agent to be embedded as a widget on websites
                                </p>
                            </div>
                            <Switch id="enable_widget" v-model="form.enable_widget" />
                        </div>

                        <!-- Enable Iframe Embedding -->
                        <div class="flex items-center justify-between space-x-2">
                            <div>
                                <Label class="text-base" for="enable_iframe_embedding">Enable Iframe embedding</Label>
                                <p class="text-sm text-muted-foreground">
                                    Allow this agent to be embedded using iframe on external websites
                                </p>
                            </div>
                            <Switch id="enable_iframe_embedding" v-model="form.enable_iframe_embedding" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Save Button -->
            <div class="flex justify-end">
                <FormSuccessMessage
                    :show="form.recentlySuccessful"
                    message="Security settings saved"
                />
                <Button :disabled="isSubmitting" size="lg" type="submit">
                    <LoaderCircle v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                    <Save v-else class="mr-2 h-4 w-4" />
                    Save Changes
                </Button>
            </div>
        </form>
    </AgentLayout>
</template>
