<script setup lang="ts">
// One tab per backend language, each showing how to sign a sign-in token in
// that language. Used for both the JWT and the HMAC scheme; `prefix` keeps the
// copy keys of the two schemes apart.
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/ui/tabs';
import CodeBlock from './CodeBlock.vue';
import type { RenderedExample } from './highlight';

defineProps<{
    blocks: RenderedExample[];
    prefix: string;
    copiedKey: string | null;
}>();

const emit = defineEmits<{ copy: [text: string, key: string] }>();
</script>

<template>
    <Tabs default-value="javascript">
        <TabsList class="mb-3 h-auto flex-wrap justify-start gap-1">
            <TabsTrigger v-for="lang in blocks" :key="lang.id" :value="lang.id">
                {{ lang.name }}
            </TabsTrigger>
        </TabsList>

        <TabsContent v-for="lang in blocks" :key="lang.id" :value="lang.id">
            <p class="text-muted-foreground mb-2 text-xs">{{ lang.hint }}</p>
            <CodeBlock
                :html="lang.highlighted"
                :language="lang.hljs"
                :copied="copiedKey === `${prefix}-${lang.id}`"
                @copy="emit('copy', lang.code, `${prefix}-${lang.id}`)"
            />
        </TabsContent>
    </Tabs>
</template>
