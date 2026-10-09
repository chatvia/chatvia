<script setup lang="ts">
/**
 * The JavaScript API reference for the Widget and Command Bar, which both
 * expose `window.chatvia.*` on the customer's page: the methods, the events
 * with a copyable example, and a one-line quick start. The events example is
 * passed in by the Deploy page, which keeps the customer-facing code samples.
 */
import { highlightCode } from '@/components/agents/deploy/highlightCode';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useSnippetClipboard } from '@/composables/agents/deploy/useSnippetClipboard';
import { Copy, Info, Terminal, Zap } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    /** Example code for the events section, shown and copied as is. */
    eventsExample: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const { copySnippet } = useSnippetClipboard();

const apiMethods = computed(() => [
    { method: 'window.chatvia.open()', description: t('agents.setup.api.methods_list.open') },
    { method: 'window.chatvia.close()', description: t('agents.setup.api.methods_list.close') },
    { method: 'window.chatvia.toggle()', description: t('agents.setup.api.methods_list.toggle') },
    { method: 'window.chatvia.isOpen()', description: t('agents.setup.api.methods_list.is_open') },
    { method: 'window.chatvia.send(msg)', description: t('agents.setup.api.methods_list.send') },
    { method: 'window.chatvia.newChat()', description: t('agents.setup.api.methods_list.new_chat') },
    { method: 'window.chatvia.destroy()', description: t('agents.setup.api.methods_list.destroy') },
    { method: 'window.chatvia.on(event, fn)', description: t('agents.setup.api.methods_list.on') },
    { method: 'window.chatvia.off(event, fn)', description: t('agents.setup.api.methods_list.off') },
]);

const highlightedEventsExample = computed(() => highlightCode(props.eventsExample, 'javascript'));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[85vh] flex-col overflow-hidden sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Terminal class="size-5" />
                    {{ t('agents.setup.api.title') }}
                </DialogTitle>
                <DialogDescription>{{ t('agents.setup.api.description') }}</DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-6 overflow-y-auto pe-2">
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Zap class="size-4 text-amber-500" />
                        <h5 class="text-sm font-medium">{{ t('agents.setup.api.methods') }}</h5>
                    </div>
                    <div class="overflow-hidden rounded-lg border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="text-muted-foreground px-3 py-2 text-start font-medium">{{ t('agents.setup.api.method_col') }}</th>
                                    <th class="text-muted-foreground px-3 py-2 text-start font-medium">
                                        {{ t('agents.setup.api.description_col') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in apiMethods" :key="item.method" class="hover:bg-muted/30 transition-colors">
                                    <td class="px-3 py-2">
                                        <code class="bg-muted text-primary rounded px-1.5 py-0.5 font-mono text-xs" dir="ltr">{{ item.method }}</code>
                                    </td>
                                    <td class="text-muted-foreground px-3 py-2">{{ item.description }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Zap class="size-4 text-emerald-500" />
                        <h5 class="text-sm font-medium">{{ t('agents.setup.api.events') }}</h5>
                    </div>
                    <p class="text-muted-foreground text-sm">
                        {{ t('agents.setup.api.events_hint') }}
                        <code class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs" dir="ltr">window.chatvia.on(event, callback)</code>
                    </p>
                    <div class="border-border bg-muted/40 overflow-hidden rounded-lg border" dir="ltr">
                        <div class="border-border bg-muted text-muted-foreground flex items-center justify-between border-b px-4 py-2">
                            <span class="text-xs font-medium">{{ t('agents.setup.api.code_language') }}</span>
                            <Button type="button" variant="ghost" size="sm" class="hover:bg-background h-7 px-2" @click="copySnippet(eventsExample)">
                                <Copy class="me-1.5 size-3.5" />
                                <span class="text-xs">{{ t('agents.deploy.copy') }}</span>
                            </Button>
                        </div>
                        <pre
                            class="hljs overflow-x-auto bg-transparent p-4 text-sm leading-relaxed"
                        ><code class="language-javascript font-mono" v-html="highlightedEventsExample" /></pre>
                    </div>
                </div>

                <Alert class="bg-primary/5 border-primary/20">
                    <Info class="text-primary size-4" />
                    <AlertDescription class="text-sm">
                        <strong>{{ t('agents.setup.api.quick_example') }}</strong> {{ t('agents.setup.api.quick_example_text') }}
                        <code class="bg-muted mt-2 block rounded px-2 py-1 font-mono text-xs" dir="ltr">
                            document.getElementById('help-btn').onclick = () =&gt; window.chatvia.open();
                        </code>
                    </AlertDescription>
                </Alert>
            </div>

            <DialogFooter class="mt-4">
                <Button variant="outline" @click="open = false">{{ t('agents.setup.api.close') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
