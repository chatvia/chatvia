<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Construction, Globe, MessageSquare, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

interface TemplatePayload {
    id: string;
    name: string;
    prompt_id: string | null;
    is_public: boolean;
    hosted_chat_config: Record<string, unknown> | null;
    widget_config: Record<string, unknown> | null;
    bar_config: Record<string, unknown> | null;
}

interface Props {
    template: TemplatePayload | null;
    prompts: { id: string; name: string }[];
}

const props = defineProps<Props>();

const isEdit = computed(() => props.template !== null);

const t = props.template;

// Persona-level fields are shared across every surface this template
// configures. Per-surface config blobs (hosted_chat / widget / bar) are
// edited via the surface tabs below; the editor passes them through as
// nested JSON to the per-surface request groups.
const form = useForm({
    name: t?.name ?? '',
    prompt_id: t?.prompt_id ?? '',
    is_public: t?.is_public ?? false,
    hosted_chat: (t?.hosted_chat_config as Record<string, unknown>) ?? {},
    widget: (t?.widget_config as Record<string, unknown>) ?? {},
    bar: (t?.bar_config as Record<string, unknown>) ?? {},
});

const activeSurface = ref<'hosted_chat' | 'widget' | 'bar'>('hosted_chat');

const showDeleteDialog = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Templates', href: '/settings/templates' },
    { title: isEdit.value ? 'Edit template' : 'New template', href: '#' },
];

const submit = () => {
    if (isEdit.value && t) {
        form.put(route('settings.templates.update', { external_id: t.id }), { preserveScroll: true });
    } else {
        form.post(route('settings.templates.store'), { preserveScroll: true });
    }
};

const deleteTemplate = () => {
    if (!t) return;
    router.delete(route('settings.templates.destroy', { external_id: t.id }));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="isEdit ? 'Edit template' : 'New template'" />

        <SettingsLayout>
            <div class="space-y-6">
                <div>
                    <h2 class="text-base font-semibold">{{ isEdit ? 'Edit template' : 'New template' }}</h2>
                    <p class="text-muted-foreground text-sm">
                        Persona settings (name, prompt, access) are shared. Each surface — Hosted Chat / Widget / Bar — is configured
                        independently below.
                    </p>
                </div>

                <form class="space-y-6" @submit.prevent="submit">
                    <!-- Persona (shared) -->
                    <section class="space-y-4 rounded-lg border p-5">
                        <h3 class="text-sm font-semibold">Persona</h3>

                        <div class="space-y-2">
                            <Label for="name" required>Template name</Label>
                            <Input id="name" v-model="form.name" :error="form.errors.name" placeholder="E.g., Customer support persona" />
                            <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="prompt_id">Library prompt</Label>
                            <Select v-model="form.prompt_id">
                                <SelectTrigger id="prompt_id">
                                    <SelectValue placeholder="No prompt attached" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">No prompt</SelectItem>
                                    <SelectItem v-for="p in prompts" :key="p.id" :value="p.id">{{ p.name }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label for="is_public" class="font-medium">Public</Label>
                                <p class="text-muted-foreground text-sm">
                                    Agents using this template load without a signed token. Off by default.
                                </p>
                            </div>
                            <Switch id="is_public" v-model="form.is_public" />
                        </div>
                    </section>

                    <!-- Per-surface tabs -->
                    <section class="space-y-4 rounded-lg border p-5">
                        <Tabs v-model="activeSurface" class="space-y-4">
                            <TabsList>
                                <TabsTrigger value="hosted_chat" class="gap-2">
                                    <MessageSquare class="h-4 w-4" />
                                    Hosted Chat
                                </TabsTrigger>
                                <TabsTrigger value="widget" class="gap-2">
                                    <Globe class="h-4 w-4" />
                                    Widget
                                </TabsTrigger>
                                <TabsTrigger value="bar" class="gap-2">
                                    <Search class="h-4 w-4" />
                                    Bar
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="hosted_chat">
                                <Alert>
                                    <Construction class="h-4 w-4" />
                                    <AlertDescription>
                                        Hosted Chat per-surface editor lands next — text, starters, branding, multi-model picker, embed
                                        config. Until then this surface is configured directly on the agent's
                                        <strong>Setup → Hosted Chat</strong> page.
                                    </AlertDescription>
                                </Alert>
                            </TabsContent>

                            <TabsContent value="widget">
                                <Alert>
                                    <Construction class="h-4 w-4" />
                                    <AlertDescription>
                                        Widget per-surface editor lands next — text, starters, branding, single-model picker, position /
                                        size / sites / show-on-load.
                                    </AlertDescription>
                                </Alert>
                            </TabsContent>

                            <TabsContent value="bar">
                                <Alert>
                                    <Construction class="h-4 w-4" />
                                    <AlertDescription>
                                        Bar per-surface editor lands next — text, single-model picker, trigger key, placement.
                                    </AlertDescription>
                                </Alert>
                            </TabsContent>
                        </Tabs>
                    </section>

                    <div class="flex items-center justify-between">
                        <Button v-if="isEdit" type="button" variant="destructive" @click="showDeleteDialog = true">
                            <Trash2 class="mr-2 h-4 w-4" />
                            Delete template
                        </Button>
                        <span v-else />

                        <div class="flex gap-2">
                            <Button type="button" variant="outline" @click="router.visit(route('settings.templates.index'))">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ isEdit ? 'Save changes' : 'Create template' }}
                            </Button>
                        </div>
                    </div>
                </form>

                <DeleteConfirmationDialog
                    v-if="t"
                    v-model:open="showDeleteDialog"
                    :title="`Delete ${t.name}?`"
                    description="Agents currently attached to this template will revert to their own settings after detach. This action cannot be undone."
                    @confirm="deleteTemplate"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
