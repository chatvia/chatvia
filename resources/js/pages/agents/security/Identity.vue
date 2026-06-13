<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import hljs from 'highlight.js/lib/core';
import cpp from 'highlight.js/lib/languages/cpp';
import csharp from 'highlight.js/lib/languages/csharp';
import go from 'highlight.js/lib/languages/go';
import java from 'highlight.js/lib/languages/java';
import javascript from 'highlight.js/lib/languages/javascript';
import kotlin from 'highlight.js/lib/languages/kotlin';
import php from 'highlight.js/lib/languages/php';
import python from 'highlight.js/lib/languages/python';
import rust from 'highlight.js/lib/languages/rust';
import swift from 'highlight.js/lib/languages/swift';
import xml from 'highlight.js/lib/languages/xml';
import { computed, ref, watch } from 'vue';

import { HMAC_EXAMPLES, JWT_EXAMPLES } from './Identity.examples';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ArrowUpRight, Check, Copy, Eye, EyeOff, RefreshCw, ShieldCheck } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const { t } = useI18n();

hljs.registerLanguage('cpp', cpp);
hljs.registerLanguage('csharp', csharp);
hljs.registerLanguage('go', go);
hljs.registerLanguage('java', java);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('kotlin', kotlin);
hljs.registerLanguage('php', php);
hljs.registerLanguage('python', python);
hljs.registerLanguage('rust', rust);
hljs.registerLanguage('swift', swift);
hljs.registerLanguage('xml', xml);

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    auth_secret: string;
    embed_script_url: string;
}

const props = defineProps<Props>();

const revealed = ref(false);

// One shared "just copied" indicator keyed by which block was copied, so each
// copy button can flash its own checkmark independently.
const copiedKey = ref<string | null>(null);

const copy = async (text: string, key: string) => {
    await navigator.clipboard.writeText(text);
    copiedKey.value = key;
    setTimeout(() => {
        if (copiedKey.value === key) copiedKey.value = null;
    }, 2000);
};

const maskedSecret = computed(() => {
    const tail = props.auth_secret.slice(-4);
    return `cvsk_${'•'.repeat(24)}${tail}`;
});

// Access control — when off, the widget refuses to load without a signed
// data-auth token. Toggling to public exposes the agent to anyone, so we
// confirm before applying that direction; going protected applies at once.
const protectionForm = useForm({ is_public: props.agent.is_public });
const makePublicOpen = ref(false);
const isProtected = computed(() => !protectionForm.is_public);

const updateProtection = () => {
    protectionForm.patch(route('agents.security.update', props.agent.external_id), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('agents.identity_verification.security_updated')),
        onError: () => {
            toast.error(t('agents.identity_verification.security_update_error'));
            protectionForm.is_public = !protectionForm.is_public;
        },
    });
};

// Going public asks for confirmation first — the switch stays OFF until the
// user clicks "Make public" in the dialog. Going protected applies immediately.
const onTogglePublic = (next: boolean) => {
    if (next) {
        makePublicOpen.value = true;
    } else {
        protectionForm.is_public = false;
        updateProtection();
    }
};

const confirmMakePublic = () => {
    makePublicOpen.value = false;
    protectionForm.is_public = true;
    updateProtection();
};

// Regenerating mints a brand-new secret server-side; the redirect back re-runs
// the page so it reloads with the new value. It also invalidates every token
// already signed with the old secret, hence the confirm dialog.
const regenerateOpen = ref(false);
const regenerateForm = useForm({});

// Typing "I understand" arms the destructive action — a deliberate pause before
// invalidating every live token. Cleared whenever the dialog closes (cancel,
// escape, outside click, or success) so a reopen always starts fresh.
const regenerateConfirmation = ref('');
const confirmPhrase = computed(() => t('agents.identity_verification.confirm_phrase'));
const canRegenerate = computed(() => regenerateConfirmation.value.trim().toLowerCase() === confirmPhrase.value.toLowerCase());

watch(regenerateOpen, (open) => {
    if (!open) regenerateConfirmation.value = '';
});

const confirmRegenerate = () => {
    regenerateForm.post(route('agents.identity.regenerate', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            regenerateOpen.value = false;
            revealed.value = false;
            toast.success(t('agents.identity_verification.secret_regenerated'));
        },
        onError: () => toast.error(t('agents.identity_verification.regenerate_error')),
    });
};

const embedSnippet = computed(() =>
    [
        '<script',
        '  id="chatbot-embed-script"',
        `  src="${props.embed_script_url}"`,
        `  data-agent-id="${props.agent.external_id}"`,
        '  data-auth="THE_TOKEN_YOU_SIGNED"',
        '  defer>',
        '</scr' + 'ipt>',
    ].join('\n'),
);

const chatLinkExample = computed(() => `${props.agent.chat_url}?token=THE_TOKEN_YOU_SIGNED`);

const embedHighlighted = computed(() => hljs.highlight(embedSnippet.value, { language: 'xml', ignoreIllegals: true }).value);

// JWT examples need the agent's external id baked into the displayed snippet —
// the file under ./Identity.examples.ts uses `__AGENT_EXTERNAL_ID__`
// as a sentinel so it can be swapped at display time without per-language
// template tricks. HMAC examples don't need substitution.
const resolveAgentId = (code: string) => code.replaceAll('__AGENT_EXTERNAL_ID__', props.agent.external_id);

type RenderedExample = (typeof JWT_EXAMPLES)[number] & { highlighted: string };

const jwtBlocks = computed<RenderedExample[]>(() =>
    JWT_EXAMPLES.map((e) => {
        const code = resolveAgentId(e.code);
        return { ...e, code, highlighted: hljs.highlight(code, { language: e.hljs, ignoreIllegals: true }).value };
    }),
);

const hmacBlocks = computed<RenderedExample[]>(() =>
    HMAC_EXAMPLES.map((e) => ({
        ...e,
        highlighted: hljs.highlight(e.code, { language: e.hljs, ignoreIllegals: true }).value,
    })),
);
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.identity_verification.title')">
        <div class="flex justify-center">
            <div class="w-full max-w-3xl space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <ShieldCheck class="size-6" />
                        {{ t('agents.identity_verification.title') }}
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        {{ t('agents.identity_verification.subtitle') }}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('agents.identity_verification.access_control') }}</CardTitle>
                        <CardDescription>
                            {{ t('agents.identity_verification.access_control_description_before') }}
                            <code>data-auth</code> {{ t('agents.identity_verification.access_control_description_after') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <label for="agent-public" class="flex cursor-pointer items-center justify-between rounded-lg border p-4">
                            <div class="space-y-0.5 pe-4">
                                <span class="text-sm leading-none font-medium">{{ t('agents.identity_verification.make_public_label') }}</span>
                                <p class="text-muted-foreground text-xs">
                                    {{ t('agents.identity_verification.make_public_hint') }}
                                </p>
                            </div>
                            <Switch id="agent-public" :model-value="protectionForm.is_public" @update:model-value="onTogglePublic" />
                        </label>
                    </CardContent>
                </Card>

                <Card v-if="isProtected">
                    <CardHeader>
                        <CardTitle>{{ t('agents.identity_verification.signing_secret_title') }}</CardTitle>
                        <CardDescription>
                            {{ t('agents.identity_verification.signing_secret_description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex flex-col gap-2">
                            <Label for="signing-secret">{{ t('agents.identity_verification.secret_label') }}</Label>
                            <div class="flex items-center gap-2">
                                <Input
                                    id="signing-secret"
                                    :model-value="revealed ? props.auth_secret : maskedSecret"
                                    readonly
                                    class="font-mono text-xs"
                                />
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button type="button" variant="outline" size="icon" @click="revealed = !revealed">
                                                <EyeOff v-if="revealed" class="size-4" />
                                                <Eye v-else class="size-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>{{
                                            revealed ? t('agents.identity_verification.hide') : t('agents.identity_verification.reveal')
                                        }}</TooltipContent>
                                    </Tooltip>

                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button type="button" variant="outline" size="icon" @click="copy(props.auth_secret, 'secret')">
                                                <Check v-if="copiedKey === 'secret'" class="size-4 text-green-600" />
                                                <Copy v-else class="size-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>{{
                                            copiedKey === 'secret' ? t('agents.identity_verification.copied') : t('agents.identity_verification.copy')
                                        }}</TooltipContent>
                                    </Tooltip>

                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button type="button" variant="outline" size="icon" @click="regenerateOpen = true">
                                                <RefreshCw class="size-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>{{ t('agents.identity_verification.regenerate_secret') }}</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- JWT/HMAC tabs sit ABOVE the Card as a section-level chooser. The Card body
                     swaps the relevant scheme's steps + code examples; a shared "Use the token"
                     block lives at the bottom of the Card and is visible regardless of selection. -->
                <Tabs v-if="isProtected" default-value="jwt" class="space-y-4">
                    <TabsList class="h-auto gap-1 p-1">
                        <TabsTrigger value="jwt" class="px-6 py-2 text-base">JWT</TabsTrigger>
                        <TabsTrigger value="hmac" class="px-6 py-2 text-base">HMAC</TabsTrigger>
                    </TabsList>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('agents.identity_verification.signin_tokens_title') }}</CardTitle>
                            <CardDescription>
                                {{ t('agents.identity_verification.signin_tokens_description') }}
                            </CardDescription>
                            <p class="text-muted-foreground mt-2 text-sm">
                                {{ t('agents.identity_verification.rest_api_before') }}
                                <a
                                    href="/docs/api#/operations/v1.agents.sign.store"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-primary inline-flex items-center gap-0.5 font-medium underline-offset-4 hover:underline"
                                >
                                    {{ t('agents.identity_verification.rest_api_link') }}<ArrowUpRight class="size-3.5" />
                                </a>
                                {{ t('agents.identity_verification.rest_api_after') }}
                            </p>
                        </CardHeader>
                        <CardContent class="space-y-8">
                            <TabsContent value="jwt" class="mt-0 space-y-6">
                                <ol class="text-muted-foreground list-decimal space-y-2 ps-5 text-sm">
                                    <li>
                                        {{ t('agents.identity_verification.jwt_step1_before') }} <code>{ sub, aud, exp }</code>
                                        {{ t('agents.identity_verification.jwt_step1_after') }}
                                    </li>
                                    <li>{{ t('agents.identity_verification.jwt_step2') }}</li>
                                    <li>
                                        {{ t('agents.identity_verification.jwt_step3_before') }}
                                        <em>{{ t('agents.identity_verification.use_token_title') }}</em>
                                        {{ t('agents.identity_verification.jwt_step3_after') }}
                                    </li>
                                    <li>{{ t('agents.identity_verification.verify_step') }}</li>
                                </ol>

                                <Tabs default-value="javascript">
                                    <TabsList class="mb-3 h-auto flex-wrap justify-start gap-1">
                                        <TabsTrigger v-for="lang in jwtBlocks" :key="lang.id" :value="lang.id">
                                            {{ lang.name }}
                                        </TabsTrigger>
                                    </TabsList>

                                    <TabsContent v-for="lang in jwtBlocks" :key="lang.id" :value="lang.id">
                                        <p class="text-muted-foreground mb-2 text-xs">{{ lang.hint }}</p>
                                        <div class="bg-muted relative rounded-lg p-4">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="absolute end-3 top-3 gap-2"
                                                @click="copy(lang.code, `jwt-${lang.id}`)"
                                            >
                                                <Check v-if="copiedKey === `jwt-${lang.id}`" class="size-4 text-green-600" />
                                                <Copy v-else class="size-4" />
                                                {{ t('agents.identity_verification.copy') }}
                                            </Button>
                                            <pre
                                                class="hljs overflow-x-auto bg-transparent p-0 text-xs"
                                            ><code :class="`language-${lang.hljs}`" v-html="lang.highlighted" /></pre>
                                        </div>
                                    </TabsContent>
                                </Tabs>
                            </TabsContent>

                            <TabsContent value="hmac" class="mt-0 space-y-6">
                                <ol class="text-muted-foreground list-decimal space-y-2 ps-5 text-sm">
                                    <li>{{ t('agents.identity_verification.hmac_step1') }}</li>
                                    <li>
                                        {{ t('agents.identity_verification.hmac_step2_before') }}
                                        <code>sig = hmac_sha256("userId.exp", secret)</code>
                                        {{ t('agents.identity_verification.hmac_step2_after') }}
                                    </li>
                                    <li>
                                        {{ t('agents.identity_verification.hmac_step3_before') }} <code>cvh1.userId.exp.sig</code>
                                        {{ t('agents.identity_verification.hmac_step3_middle') }}
                                        <em>{{ t('agents.identity_verification.use_token_title') }}</em>
                                        {{ t('agents.identity_verification.hmac_step3_after') }}
                                    </li>
                                    <li>{{ t('agents.identity_verification.verify_step') }}</li>
                                </ol>

                                <Tabs default-value="javascript">
                                    <TabsList class="mb-3 h-auto flex-wrap justify-start gap-1">
                                        <TabsTrigger v-for="lang in hmacBlocks" :key="lang.id" :value="lang.id">
                                            {{ lang.name }}
                                        </TabsTrigger>
                                    </TabsList>

                                    <TabsContent v-for="lang in hmacBlocks" :key="lang.id" :value="lang.id">
                                        <p class="text-muted-foreground mb-2 text-xs">{{ lang.hint }}</p>
                                        <div class="bg-muted relative rounded-lg p-4">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="absolute end-3 top-3 gap-2"
                                                @click="copy(lang.code, `hmac-${lang.id}`)"
                                            >
                                                <Check v-if="copiedKey === `hmac-${lang.id}`" class="size-4 text-green-600" />
                                                <Copy v-else class="size-4" />
                                                {{ t('agents.identity_verification.copy') }}
                                            </Button>
                                            <pre
                                                class="hljs overflow-x-auto bg-transparent p-0 text-xs"
                                            ><code :class="`language-${lang.hljs}`" v-html="lang.highlighted" /></pre>
                                        </div>
                                    </TabsContent>
                                </Tabs>
                            </TabsContent>

                            <!-- "Use the token" — shared between both schemes. The signed token plugs into the same
                                 two places either way: widget data-auth, or hosted chat link with ?token=. -->
                            <div class="space-y-4 border-t pt-6">
                                <div>
                                    <p class="text-sm font-medium">{{ t('agents.identity_verification.use_token_title') }}</p>
                                    <p class="text-muted-foreground mt-1 text-sm">
                                        {{ t('agents.identity_verification.use_token_before') }} <code>data-auth</code
                                        >{{ t('agents.identity_verification.use_token_middle') }} <code>?token=</code
                                        >{{ t('agents.identity_verification.use_token_after') }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-muted-foreground mb-2 text-xs">{{ t('agents.identity_verification.widget_embed') }}</p>
                                    <div class="bg-muted relative rounded-lg p-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            class="absolute end-3 top-3 gap-2"
                                            @click="copy(embedSnippet, 'embed')"
                                        >
                                            <Check v-if="copiedKey === 'embed'" class="size-4 text-green-600" />
                                            <Copy v-else class="size-4" />
                                            {{ t('agents.identity_verification.copy') }}
                                        </Button>
                                        <pre
                                            class="hljs overflow-x-auto bg-transparent p-0 text-xs"
                                        ><code class="language-xml" v-html="embedHighlighted" /></pre>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-muted-foreground mb-2 text-xs">{{ t('agents.identity_verification.hosted_chat_link') }}</p>
                                    <div class="bg-muted rounded-lg p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <code class="text-foreground flex-1 font-mono text-xs break-all">{{ chatLinkExample }}</code>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="shrink-0 gap-2"
                                                @click="copy(chatLinkExample, 'chatLink')"
                                            >
                                                <Check v-if="copiedKey === 'chatLink'" class="size-4 text-green-600" />
                                                <Copy v-else class="size-4" />
                                                {{ t('agents.identity_verification.copy') }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Tabs>
            </div>
        </div>

        <!-- Make public confirmation — flipping to public exposes the agent so we double-check -->
        <AlertDialog v-model:open="makePublicOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('agents.identity_verification.make_public_dialog_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('agents.identity_verification.make_public_dialog_description') }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('agents.identity_verification.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction @click="confirmMakePublic">{{ t('agents.identity_verification.make_public_confirm') }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Regenerate confirmation -->
        <AlertDialog v-model:open="regenerateOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('agents.identity_verification.regenerate_dialog_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('agents.identity_verification.regenerate_dialog_before') }}
                        <span class="font-medium">{{ t('agents.identity_verification.regenerate_dialog_emphasis') }}</span>
                        {{ t('agents.identity_verification.regenerate_dialog_after') }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div class="grid gap-2">
                    <Label for="regenerate-confirmation">{{
                        t('agents.identity_verification.regenerate_confirm_label', { phrase: confirmPhrase })
                    }}</Label>
                    <Input id="regenerate-confirmation" v-model="regenerateConfirmation" :placeholder="confirmPhrase" autocomplete="off" />
                </div>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('agents.identity_verification.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="!canRegenerate || regenerateForm.processing"
                        @click="confirmRegenerate"
                    >
                        {{ t('agents.identity_verification.regenerate') }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AgentLayout>
</template>
