<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import hljs from 'highlight.js/lib/core';
import javascript from 'highlight.js/lib/languages/javascript';
import php from 'highlight.js/lib/languages/php';
import xml from 'highlight.js/lib/languages/xml';
import { computed, ref, watch } from 'vue';

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
import AgentLayout from '@/layouts/AgentLayout.vue';
import { AlertCircle, Check, Copy, Eye, EyeOff, RefreshCw, ShieldCheck } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('php', php);
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
        onSuccess: () => toast.success('Security settings updated'),
        onError: () => {
            toast.error('Failed to update security settings');
            protectionForm.is_public = !protectionForm.is_public;
        },
    });
};

const confirmMakePublic = () => {
    makePublicOpen.value = false;
    updateProtection();
};

const cancelMakePublic = () => {
    makePublicOpen.value = false;
    protectionForm.is_public = false;
};

watch(
    () => protectionForm.is_public,
    (newValue, oldValue) => {
        if (oldValue !== undefined && newValue !== oldValue) {
            if (newValue === true) {
                makePublicOpen.value = true;
            } else {
                updateProtection();
            }
        }
    },
);

// Regenerating mints a brand-new secret server-side; the redirect back re-runs
// the page so it reloads with the new value. It also invalidates every token
// already signed with the old secret, hence the confirm dialog.
const regenerateOpen = ref(false);
const regenerateForm = useForm({});

const confirmRegenerate = () => {
    regenerateForm.post(route('agents.identity.regenerate', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            regenerateOpen.value = false;
            revealed.value = false;
            toast.success('Signing secret regenerated');
        },
        onError: () => toast.error('Could not regenerate the secret'),
    });
};

const nodeExample = computed(() =>
    [
        "const jwt = require('jsonwebtoken');",
        '',
        "// This agent's signing secret. Keep it on your server — never expose it",
        '// to the browser or ship it in client-side code.',
        "const SIGNING_SECRET = 'YOUR_SIGNING_SECRET';",
        '',
        'function signChatviaToken(userId, ttlSeconds = 3600) {',
        '  return jwt.sign(',
        '    {',
        '      sub: userId,   // the id of the user you are signing in',
        `      aud: '${props.agent.external_id}',  // this agent's id`,
        '    },',
        '    SIGNING_SECRET,',
        "    { algorithm: 'HS256', expiresIn: ttlSeconds },",
        '  );',
        '}',
        '',
        '// Mint a token, then hand it to the widget as data-auth.',
        "const token = signChatviaToken('user_123');",
    ].join('\n'),
);

const phpExample = computed(() =>
    [
        '<?php',
        '',
        'use Firebase\\JWT\\JWT;',
        '',
        "// This agent's signing secret. Keep it on your server — never expose it",
        '// to the browser or ship it in client-side code.',
        "$secret = 'YOUR_SIGNING_SECRET';",
        '',
        'function sign_chatvia_token(string $secret, string $userId, int $ttlSeconds = 3600): string',
        '{',
        '    return JWT::encode([',
        "        'sub' => $userId,   // the id of the user you are signing in",
        `        'aud' => '${props.agent.external_id}',  // this agent's id`,
        "        'exp' => time() + $ttlSeconds,",
        "    ], $secret, 'HS256');",
        '}',
        '',
        '// Mint a token, then hand it to the widget as data-auth.',
        "$token = sign_chatvia_token($secret, 'user_123');",
    ].join('\n'),
);

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

const nodeHighlighted = computed(() => hljs.highlight(nodeExample.value, { language: 'javascript', ignoreIllegals: true }).value);
const phpHighlighted = computed(() => hljs.highlight(phpExample.value, { language: 'php', ignoreIllegals: true }).value);
const embedHighlighted = computed(() => hljs.highlight(embedSnippet.value, { language: 'xml', ignoreIllegals: true }).value);
</script>

<template>
    <AgentLayout :agent="agent" title="Security">
        <div class="flex justify-center">
            <div class="w-full max-w-6xl space-y-6">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <ShieldCheck class="size-6" />
                        Security
                    </h1>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Control who can load this agent and verify the visitors you do let in. Toggle access control below; when the agent is
                        protected, mint short-lived sign-in tokens on your backend with the signing secret further down the page.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Access control</CardTitle>
                        <CardDescription>
                            When public, anyone can load this agent. When protected, the widget and chat link require a signed
                            <code>data-auth</code> token to load.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center justify-between rounded-lg border p-4">
                            <div class="space-y-0.5 pr-4">
                                <Label for="agent-public">Make this agent public</Label>
                                <p class="text-muted-foreground text-xs">
                                    Off keeps the agent protected and requires a signed token for every visitor.
                                </p>
                            </div>
                            <Switch id="agent-public" v-model="protectionForm.is_public" />
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="isProtected">
                    <CardHeader>
                        <CardTitle>Your signing secret</CardTitle>
                        <CardDescription>
                            One secret per agent. Store it as an environment variable on your server. Anyone with it can sign in as any of your users
                            on this agent, so treat it like a password.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex flex-col gap-2">
                            <Label for="signing-secret">Secret</Label>
                            <div class="flex items-center gap-2">
                                <Input
                                    id="signing-secret"
                                    :model-value="revealed ? props.auth_secret : maskedSecret"
                                    readonly
                                    class="font-mono text-xs"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    :title="revealed ? 'Hide' : 'Reveal'"
                                    @click="revealed = !revealed"
                                >
                                    <EyeOff v-if="revealed" class="size-4" />
                                    <Eye v-else class="size-4" />
                                </Button>
                                <Button type="button" variant="outline" size="icon" title="Copy" @click="copy(props.auth_secret, 'secret')">
                                    <Check v-if="copiedKey === 'secret'" class="size-4 text-green-600" />
                                    <Copy v-else class="size-4" />
                                </Button>
                            </div>
                        </div>

                        <div
                            class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                        >
                            <AlertCircle class="mt-0.5 size-4 flex-shrink-0" />
                            <span>
                                Regenerating immediately invalidates every token signed with the current secret for this agent — across both chat
                                links and the widget — so you'll need to redeploy your backend with the new value.
                            </span>
                        </div>

                        <div>
                            <Button type="button" variant="outline" @click="regenerateOpen = true">
                                <RefreshCw class="mr-2 size-4" />
                                Regenerate secret
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="isProtected">
                    <CardHeader>
                        <CardTitle>How it works</CardTitle>
                        <CardDescription> You only need this when this agent is marked as protected. Public agents need no token. </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ol class="text-muted-foreground list-decimal space-y-2 pl-5 text-sm">
                            <li>
                                On your server, build a payload of <code>{ sub, aud, exp }</code> — the user's id, this agent's id, and an expiry.
                            </li>
                            <li>Sign it as a JWT (HS256) with this agent's secret to produce a token (see the examples below).</li>
                            <li>Pass the token to the widget as <code>data-auth</code>, or append <code>?token=</code> to a chat link.</li>
                            <li>
                                Chatvia verifies the signature and signs that user in. Tokens last at most one hour — mint a fresh one per page load.
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <Card v-if="isProtected">
                    <CardHeader>
                        <CardTitle>Sign a token</CardTitle>
                        <CardDescription> A standard JWT (HS256) in either language — run it on your server, never in the browser. </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Tabs default-value="javascript">
                            <TabsList class="mb-3">
                                <TabsTrigger value="javascript">JavaScript</TabsTrigger>
                                <TabsTrigger value="php">PHP</TabsTrigger>
                            </TabsList>

                            <TabsContent value="javascript">
                                <p class="text-muted-foreground mb-2 text-xs">Using the <code>jsonwebtoken</code> package.</p>
                                <div class="bg-muted relative rounded-lg p-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="absolute top-3 right-3 gap-2"
                                        @click="copy(nodeExample, 'node')"
                                    >
                                        <Check v-if="copiedKey === 'node'" class="size-4 text-green-600" />
                                        <Copy v-else class="size-4" />
                                        Copy
                                    </Button>
                                    <pre
                                        class="hljs overflow-x-auto bg-transparent p-0 text-xs"
                                    ><code class="language-javascript" v-html="nodeHighlighted" /></pre>
                                </div>
                            </TabsContent>

                            <TabsContent value="php">
                                <p class="text-muted-foreground mb-2 text-xs">Using the <code>firebase/php-jwt</code> package.</p>
                                <div class="bg-muted relative rounded-lg p-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="absolute top-3 right-3 gap-2"
                                        @click="copy(phpExample, 'php')"
                                    >
                                        <Check v-if="copiedKey === 'php'" class="size-4 text-green-600" />
                                        <Copy v-else class="size-4" />
                                        Copy
                                    </Button>
                                    <pre
                                        class="hljs overflow-x-auto bg-transparent p-0 text-xs"
                                    ><code class="language-php" v-html="phpHighlighted" /></pre>
                                </div>
                            </TabsContent>
                        </Tabs>
                    </CardContent>
                </Card>

                <Card v-if="isProtected">
                    <CardHeader>
                        <CardTitle>Add the token to your embed</CardTitle>
                        <CardDescription>
                            Take the embed snippet from this agent's Setup tab and add a <code>data-auth</code> attribute with the token you signed.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="bg-muted relative rounded-lg p-4">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="absolute top-3 right-3 gap-2"
                                @click="copy(embedSnippet, 'embed')"
                            >
                                <Check v-if="copiedKey === 'embed'" class="size-4 text-green-600" />
                                <Copy v-else class="size-4" />
                                Copy
                            </Button>
                            <pre class="hljs overflow-x-auto bg-transparent p-0 text-xs"><code class="language-xml" v-html="embedHighlighted" /></pre>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Make public confirmation — flipping to public exposes the agent so we double-check -->
        <AlertDialog v-model:open="makePublicOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Make agent public?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Anyone will be able to load this agent's widget or chat link without a signed token. You can switch it back to protected at
                        any time.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="cancelMakePublic">Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="confirmMakePublic">Make public</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Regenerate confirmation -->
        <AlertDialog v-model:open="regenerateOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Regenerate signing secret</AlertDialogTitle>
                    <AlertDialogDescription>
                        This generates a new secret for this agent and
                        <span class="font-medium">invalidates every token signed with the current one</span> — across this agent's chat links and
                        widget. Sign-in will break until you redeploy your backend with the new secret. This cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        :disabled="regenerateForm.processing"
                        @click="confirmRegenerate"
                    >
                        Regenerate
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AgentLayout>
</template>
