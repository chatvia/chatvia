<script setup lang="ts">
/**
 * "Website crawler": the form that adds a website. The user picks the
 * protocol, types the address and may limit the crawl to (or exclude) some
 * paths. Emits `added` once the server accepted the website, so the page can
 * reload its list.
 */
import InputError from '@/shared/components/InputError.vue';
import { Button } from '@/shared/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/ui/card';
import { Collapsible, CollapsibleContent } from '@/shared/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/shared/ui/dropdown-menu';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { store as storeWebsite } from '@/routes/agents/sources/websites';
import { useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Globe, LoaderCircle, Settings } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

type Protocol = 'https://' | 'http://';

const MAX_URL_LENGTH = 2048;

const props = defineProps<{
    agentId: string;
}>();

const emit = defineEmits<{
    added: [];
}>();

const { t } = useI18n();

const form = useForm({
    url: '',
    // The crawler always respects robots.txt.
    robots: true as boolean,
    include_paths: '',
    exclude_paths: '',
});

const protocol = ref<Protocol>('https://');
const settingsExpanded = ref(false);

const fullUrl = computed(() => protocol.value + form.url.trim());

const isValidUrl = computed(() => {
    try {
        new URL(fullUrl.value);
        return true;
    } catch {
        return false;
    }
});

/** Errors that have no field of their own in the form are listed under it. */
const hasOtherErrors = computed(() => form.hasErrors && !form.errors.url && !form.errors.include_paths && !form.errors.exclude_paths);

/** The toast for a rejected submit: the first field error, prefixed with its field. */
function errorToast(errors: Record<string, string>): string {
    if (errors.url) return t('agents.sources.web.error_url_prefixed', { error: errors.url });
    if (errors.robots) return t('agents.sources.web.error_settings_prefixed', { error: errors.robots });
    if (errors.include_paths) return t('agents.sources.web.error_include_paths_prefixed', { error: errors.include_paths });
    if (errors.exclude_paths) return t('agents.sources.web.error_exclude_paths_prefixed', { error: errors.exclude_paths });

    return Object.values(errors).find((message) => typeof message === 'string') ?? t('agents.sources.web.add_failed');
}

function addWebsite(): void {
    if (!form.url.trim()) {
        toast.error(t('agents.sources.web.error_enter_url'));
        return;
    }
    if (!isValidUrl.value) {
        toast.error(t('agents.sources.web.error_invalid_url'));
        return;
    }
    if (form.url.length > MAX_URL_LENGTH) {
        toast.error(t('agents.sources.web.error_url_too_long'));
        return;
    }

    form.url = fullUrl.value;

    try {
        form.post(storeWebsite.url(props.agentId), {
            preserveScroll: true,
            onSuccess: () => {
                emit('added');
                toast.success(t('agents.sources.web.added'));
                form.url = '';
            },
            onError: (errors) => toast.error(errorToast(errors)),
        });
    } catch {
        toast.error(t('agents.sources.web.unexpected_error'));
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        <Globe class="h-5 w-5" />
                        {{ t('agents.sources.web.crawler_title') }}
                    </CardTitle>
                    <CardDescription>{{ t('agents.sources.web.crawler_description') }}</CardDescription>
                </div>
                <Button variant="outline" size="sm" @click="settingsExpanded = !settingsExpanded">
                    <Settings class="me-2 h-4 w-4" />
                    {{ t('agents.sources.web.settings') }}
                    <ChevronDown v-if="!settingsExpanded" class="ms-1 h-4 w-4" />
                    <ChevronUp v-else class="ms-1 h-4 w-4" />
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <div class="space-y-3">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="flex">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        class="bg-muted border-input hover:bg-muted/80 focus:ring-ring flex h-9 items-center rounded-s-md border border-e-0 px-3 py-1 font-mono text-sm focus:ring-1 focus:ring-offset-0 focus:outline-none"
                                    >
                                        {{ protocol }}
                                        <ChevronDown class="text-muted-foreground ms-1 h-3 w-3" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuItem @click="protocol = 'https://'">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-2 rounded-full bg-green-500"></div>
                                            https://
                                        </div>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="protocol = 'http://'">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-2 rounded-full bg-orange-500"></div>
                                            http://
                                        </div>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <Input
                                v-model="form.url"
                                placeholder="www.example.com"
                                :disabled="form.processing"
                                :class="['rounded-s-none border-s-0 focus:border-s-0 focus:ring-offset-0', { 'border-red-500': form.errors.url }]"
                                class="flex-1"
                                @keyup.enter="addWebsite"
                            />
                        </div>
                        <InputError :message="form.errors.url" />
                    </div>

                    <Button :disabled="!isValidUrl || form.processing" @click="addWebsite">
                        <LoaderCircle v-if="form.processing" class="me-2 h-4 w-4 animate-spin" />
                        <Globe v-else class="me-2 h-4 w-4" />
                        {{ form.processing ? t('agents.sources.web.fetching') : t('agents.sources.web.fetch_links') }}
                    </Button>
                </div>

                <Collapsible v-model:open="settingsExpanded">
                    <CollapsibleContent>
                        <div class="bg-muted/20 space-y-4 rounded-lg border p-4">
                            <div class="mb-3">
                                <h3 class="text-sm font-semibold">{{ t('agents.sources.web.crawl_settings') }}</h3>
                            </div>

                            <div class="space-y-2">
                                <Label for="include-paths">{{ t('agents.sources.web.include_paths_label') }}</Label>
                                <Input
                                    id="include-paths"
                                    v-model="form.include_paths"
                                    type="text"
                                    :placeholder="t('agents.sources.web.paths_placeholder')"
                                    :class="{ 'border-red-500': form.errors.include_paths }"
                                    class="text-sm"
                                />
                                <InputError :message="form.errors.include_paths" />
                                <div class="text-muted-foreground text-xs">{{ t('agents.sources.web.include_paths_hint') }}</div>
                            </div>

                            <div class="space-y-2">
                                <Label for="exclude-paths">{{ t('agents.sources.web.exclude_paths_label') }}</Label>
                                <Input
                                    id="exclude-paths"
                                    v-model="form.exclude_paths"
                                    type="text"
                                    :placeholder="t('agents.sources.web.paths_placeholder')"
                                    :class="{ 'border-red-500': form.errors.exclude_paths }"
                                    class="text-sm"
                                />
                                <InputError :message="form.errors.exclude_paths" />
                                <div class="text-muted-foreground text-xs">{{ t('agents.sources.web.exclude_paths_hint') }}</div>
                            </div>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                <div v-if="hasOtherErrors" class="space-y-1">
                    <div v-for="(error, field) in form.errors" :key="field" class="text-sm text-red-600">
                        {{ Array.isArray(error) ? error[0] : error }}
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
