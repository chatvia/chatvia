<script setup lang="ts">
// How a customer signs in their own users: pick the JWT or HMAC scheme, follow
// the steps with a code sample in their backend language, then pass the token
// to the widget (`data-auth`) or the hosted chat link (`?token=`).
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ArrowUpRight, Check, Copy } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CodeBlock from './CodeBlock.vue';
import { highlight, type RenderedExample } from './highlight';
import { HMAC_EXAMPLES, JWT_EXAMPLES } from './Identity.examples';
import TokenExampleTabs from './TokenExampleTabs.vue';

const props = defineProps<{
    agentExternalId: string;
    chatUrl: string;
    embedScriptUrl: string;
    copiedKey: string | null;
}>();

const emit = defineEmits<{ copy: [text: string, key: string] }>();

const { t } = useI18n();

const embedSnippet = computed(() =>
    [
        '<script',
        '  id="chatbot-embed-script"',
        `  src="${props.embedScriptUrl}"`,
        `  data-agent-id="${props.agentExternalId}"`,
        '  data-auth="THE_TOKEN_YOU_SIGNED"',
        '  defer>',
        '</scr' + 'ipt>',
    ].join('\n'),
);

const chatLinkExample = computed(() => `${props.chatUrl}?token=THE_TOKEN_YOU_SIGNED`);

const embedHighlighted = computed(() => highlight(embedSnippet.value, 'xml'));

// JWT examples need the agent's external id baked into the displayed snippet —
// the file under ./Identity.examples.ts uses `__AGENT_EXTERNAL_ID__`
// as a sentinel so it can be swapped at display time without per-language
// template tricks. HMAC examples don't need substitution.
const resolveAgentId = (code: string) => code.replaceAll('__AGENT_EXTERNAL_ID__', props.agentExternalId);

const jwtBlocks = computed<RenderedExample[]>(() =>
    JWT_EXAMPLES.map((e) => {
        const code = resolveAgentId(e.code);
        return { ...e, code, highlighted: highlight(code, e.hljs) };
    }),
);

const hmacBlocks = computed<RenderedExample[]>(() =>
    HMAC_EXAMPLES.map((e) => ({
        ...e,
        highlighted: highlight(e.code, e.hljs),
    })),
);
</script>

<template>
    <!-- JWT/HMAC tabs sit ABOVE the Card as a section-level chooser. The Card body
         swaps the relevant scheme's steps + code examples; a shared "Use the token"
         block lives at the bottom of the Card and is visible regardless of selection. -->
    <Tabs default-value="jwt" class="space-y-4">
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

                    <TokenExampleTabs :blocks="jwtBlocks" prefix="jwt" :copied-key="copiedKey" @copy="(text, key) => emit('copy', text, key)" />
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

                    <TokenExampleTabs :blocks="hmacBlocks" prefix="hmac" :copied-key="copiedKey" @copy="(text, key) => emit('copy', text, key)" />
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
                        <CodeBlock
                            :html="embedHighlighted"
                            language="xml"
                            :copied="copiedKey === 'embed'"
                            @copy="emit('copy', embedSnippet, 'embed')"
                        />
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
                                    @click="emit('copy', chatLinkExample, 'chatLink')"
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
</template>
