<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Bot, LoaderCircle, Save } from 'lucide-vue-next';
import axios from 'axios';
import FormSuccessMessage from '@/components/FormSuccessMessage.vue';

interface Props {
    agent: any
}

const props = defineProps<Props>();
const isGeneratingSubdomain = ref(false);

const form = useForm({
    name: props.agent.name,
    sub_domain: props.agent.sub_domain,
    system_instructions: props.agent.system_instructions,
    is_public: props.agent.is_public,
});

const isSubmitting = ref(false);

const updateGeneralSettings = () => {
    isSubmitting.value = true;
    form.put(route('agents.update', props.agent.id), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const generateNewSubdomain = () => {
    isGeneratingSubdomain.value = true;
    axios.get(route('generate-random-subdomain')).then((response) => {
        isGeneratingSubdomain.value = false;
        form.sub_domain = response.data.name;
    });
}
</script>

<template>
    <AgentLayout :agent="agent" title="General Settings">
        <form @submit.prevent="updateGeneralSettings" class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Bot class="h-5 w-5" />
                        Basic Information
                    </CardTitle>
                    <CardDescription>
                        Update your agent's identity and purpose
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="name" required>Agent Name</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    :error="form.errors.name"
                                    placeholder="E.g., Customer Support Assistant"
                                />
                                <p v-if="form.errors.name" class="text-sm text-destructive">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="domain" required>Domain</Label>
                                <div class="flex items-center">
                                    <Input
                                        id="domain"
                                        v-model="form.sub_domain"
                                        :error="form.errors.sub_domain"
                                        class="rounded-r-none disabled"
                                        placeholder=""
                                        :readonly="true"
                                    />
                                    <div
                                        class="border-input bg-muted text-muted-foreground flex h-9 items-center rounded-r-md border border-l-0 px-3 text-sm"
                                    >
                                        .chatvia.ai
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        class="ml-1 h-6 w-6 p-0"
                                        size="icon"
                                        @click="generateNewSubdomain"
                                        :disabled="isGeneratingSubdomain"
                                    >
                                        <LoaderCircle v-if="isGeneratingSubdomain" class="h-3.5 w-3.5 animate-spin" />
                                        <svg v-else class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2" />
                                        </svg>
                                        <span class="sr-only">Generate new subdomain</span>
                                    </Button>
                                </div>
                                <p v-if="form.errors.sub_domain" class="text-sm text-destructive">
                                    {{ form.errors.sub_domain }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="instructions" required>System Instructions</Label>
                            <Textarea
                                id="instructions"
                                v-model="form.system_instructions"
                                :error="form.errors.system_instructions"
                                placeholder="You are a helpful assistant that specializes in..."
                                rows="6"
                            />
                            <p v-if="form.errors.system_instructions" class="text-sm text-destructive">
                                {{ form.errors.system_instructions }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                These instructions tell the AI how to behave, what tone to use, and what its purpose is
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Visibility Settings -->
            <Card>
                <CardHeader>
                    <CardTitle>Visibility Settings</CardTitle>
                    <CardDescription>
                        Control who can access your agent
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between space-x-2">
                        <div>
                            <Label class="text-base" for="is_public">Make this agent public</Label>
                            <p class="text-sm text-muted-foreground">
                                Public agents can be accessed by anyone with the link
                            </p>
                        </div>
                        <Switch id="is_public" v-model="form.is_public" />
                    </div>
                </CardContent>
            </Card>

            <!-- Save Button -->
            <div class="flex justify-end">
                <FormSuccessMessage
                    :show="form.recentlySuccessful"
                    message="Saved"
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
