<script lang="ts" setup>
/**
 * The agent's Persona page: name, chat address and instructions — either a saved
 * library prompt or inline text (mutually exclusive), saved together.
 */
import { generateAgentSlug } from '@/api/agentPersona';
import HeaderActions from '@/components/HeaderActions.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { update as updateAgent } from '@/routes/agents';
import { edit as editPrompts } from '@/routes/library/prompts';
import { Link, useForm } from '@inertiajs/vue3';
import { Bot, LoaderCircle, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    prompts: Domain.Agent.Prompt.Data.Resources.AgentPromptPickerData[];
}

const props = defineProps<Props>();
const isGeneratingSlug = ref(false);

// 'saved' = use a reusable library prompt (prompt_id); 'custom' = write
// instructions inline (system_instructions). The two are mutually exclusive.
const initialMode: 'saved' | 'custom' = props.agent.prompt_id ? 'saved' : 'custom';
const mode = ref<'saved' | 'custom'>(initialMode);

const form = useForm({
    name: props.agent.name,
    slug: props.agent.slug,
    prompt_id: props.agent.prompt_id ?? null,
    system_instructions: props.agent.system_instructions,
});

const selectedPrompt = computed(() => props.prompts.find((p) => p.id === form.prompt_id) ?? null);

const isSubmitting = ref(false);

const updateGeneralSettings = () => {
    isSubmitting.value = true;
    // Send only the active prompt source so the backend can apply mutual
    // exclusivity (prompt_id and system_instructions can't both be present).
    form.transform((data) => ({
        name: data.name,
        slug: data.slug,
        ...(mode.value === 'saved' ? { prompt_id: data.prompt_id } : { system_instructions: data.system_instructions }),
    })).patch(updateAgent.url(props.agent.external_id), {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const generateNewSlug = async () => {
    isGeneratingSlug.value = true;
    try {
        form.slug = await generateAgentSlug();
    } finally {
        // Also on failure, so the button doesn't spin forever.
        isGeneratingSlug.value = false;
    }
};

// Computed properties for header actions
const hasUnsavedChanges = computed(() => {
    const promptChanged =
        mode.value !== initialMode ||
        (mode.value === 'saved' ? form.prompt_id !== (props.agent.prompt_id ?? null) : form.system_instructions !== props.agent.system_instructions);

    return form.name !== props.agent.name || form.slug !== props.agent.slug || promptChanged;
});

const hasFormErrors = computed(() => {
    return Object.keys(form.errors).length > 0;
});
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.persona.title')">
        <template #header-actions>
            <HeaderActions
                :is-submitting="isSubmitting"
                :has-unsaved-changes="hasUnsavedChanges"
                :show-success-message="form.recentlySuccessful"
                :success-message="t('agents.persona.saved')"
                :show-error-message="hasFormErrors"
                :error-message="t('agents.persona.fix_errors')"
                :save-label="t('agents.persona.save')"
                :save-icon="Save"
                form-id="persona-form"
            />
        </template>
        <form id="persona-form" @submit.prevent="updateGeneralSettings" class="space-y-6">
            <div class="flex justify-center">
                <Card class="w-full max-w-6xl">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Bot class="h-5 w-5" />
                            {{ t('agents.persona.card_title') }}
                        </CardTitle>
                        <CardDescription>{{ t('agents.persona.card_description') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="name" required>{{ t('agents.persona.name_label') }}</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        :error="form.errors.name"
                                        :placeholder="t('agents.persona.name_placeholder')"
                                    />
                                    <p v-if="form.errors.name" class="text-destructive text-sm">
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="slug" required>{{ t('agents.persona.slug_label') }}</Label>
                                    <div class="flex items-center" dir="ltr">
                                        <div
                                            class="border-input bg-muted text-muted-foreground flex h-9 max-w-[60%] items-center truncate rounded-s-md border border-e-0 px-3 text-sm"
                                        >
                                            {{ $page.props.chatBaseUrl }}/
                                        </div>
                                        <Input
                                            id="slug"
                                            v-model="form.slug"
                                            :error="form.errors.slug"
                                            class="min-w-0 rounded-s-none"
                                            placeholder=""
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            class="ms-1 h-6 w-6 p-0"
                                            size="icon"
                                            :aria-label="t('agents.persona.generate_slug')"
                                            @click="generateNewSlug"
                                            :disabled="isGeneratingSlug"
                                        >
                                            <LoaderCircle v-if="isGeneratingSlug" class="h-3.5 w-3.5 animate-spin" />
                                            <svg
                                                v-else
                                                class="h-3.5 w-3.5"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2" />
                                            </svg>
                                        </Button>
                                    </div>
                                    <p v-if="form.errors.slug" class="text-destructive text-sm">
                                        {{ form.errors.slug }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label required>{{ t('agents.persona.instructions_label') }}</Label>
                                <p class="text-muted-foreground text-sm">
                                    {{ t('agents.persona.instructions_hint') }}
                                </p>
                                <Tabs v-model="mode" class="mt-1">
                                    <TabsList class="grid w-full max-w-sm grid-cols-2">
                                        <TabsTrigger value="saved">{{ t('agents.persona.saved_prompt') }}</TabsTrigger>
                                        <TabsTrigger value="custom">{{ t('agents.persona.custom') }}</TabsTrigger>
                                    </TabsList>

                                    <TabsContent value="saved" class="mt-4 space-y-3">
                                        <div v-if="prompts.length === 0" class="text-muted-foreground rounded-lg border py-8 text-center text-sm">
                                            {{ t('agents.persona.no_prompts_prefix') }}
                                            <Link :href="editPrompts.url()" class="text-foreground underline underline-offset-2">{{
                                                t('agents.persona.create_one')
                                            }}</Link>
                                            {{ t('agents.persona.no_prompts_suffix') }}
                                        </div>
                                        <template v-else>
                                            <Select v-model="form.prompt_id">
                                                <SelectTrigger class="w-full max-w-sm" :aria-invalid="!!form.errors.prompt_id">
                                                    <SelectValue :placeholder="t('agents.persona.select_prompt')" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="p in prompts" :key="p.id" :value="p.id">{{ p.name }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <div v-if="selectedPrompt" class="bg-muted/40 max-h-72 overflow-y-auto rounded-md border p-4">
                                                <p class="text-muted-foreground mb-2 text-xs font-medium tracking-wide uppercase">
                                                    {{ selectedPrompt.name }}
                                                </p>
                                                <pre class="text-foreground font-sans text-sm whitespace-pre-wrap">{{ selectedPrompt.prompt }}</pre>
                                            </div>
                                            <p v-if="form.errors.prompt_id" class="text-destructive text-sm">
                                                {{ form.errors.prompt_id }}
                                            </p>
                                        </template>
                                    </TabsContent>

                                    <TabsContent value="custom" class="mt-4 grid gap-2">
                                        <Textarea
                                            id="instructions"
                                            v-model="form.system_instructions"
                                            :error="form.errors.system_instructions"
                                            :placeholder="t('agents.persona.instructions_placeholder')"
                                            rows="15"
                                        />
                                        <p v-if="form.errors.system_instructions" class="text-destructive text-sm">
                                            {{ form.errors.system_instructions }}
                                        </p>
                                        <p class="text-muted-foreground text-sm">
                                            {{ t('agents.persona.instructions_help') }}
                                        </p>
                                    </TabsContent>
                                </Tabs>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </form>
    </AgentLayout>
</template>
