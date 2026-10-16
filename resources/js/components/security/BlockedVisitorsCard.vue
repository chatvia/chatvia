<script setup lang="ts">
// The visitors blocked from chatting with the workspace's agents, made from
// an agent's Guardrails activity. Each row says who (the start of the
// fingerprint, browser, system, country), where, why and until when, with an
// Unblock button. Shown on the Security page, an agent's Guardrails "Blocked"
// tab and its Security → Blocked page; the parent drops an unblocked row.
import { unblockVisitor, type BlockedVisitor } from '@/api/blocking';
import { errorMessage } from '@/api/http';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useDateFormat } from '@/composables/useDateFormat';
import { Ban, LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

defineProps<{
    blocks: BlockedVisitor[];
    canManage: boolean;
}>();

const emit = defineEmits<{
    unblocked: [blockId: string];
}>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

const removing = ref<string | null>(null);

const unblock = async (block: BlockedVisitor) => {
    removing.value = block.external_id;
    try {
        await unblockVisitor(block.external_id);
        emit('unblocked', block.external_id);
        toast.success(t('workspace.security.blocked_visitors.unblocked'));
    } catch (error) {
        toast.error(errorMessage(error, t('workspace.security.blocked_visitors.unblock_error')));
    } finally {
        removing.value = null;
    }
};

/** "Chrome on macOS · Germany", leaving out what is unknown. */
const device = (block: BlockedVisitor) =>
    [
        block.browser && block.os
            ? t('workspace.security.blocked_visitors.browser_on_os', { browser: block.browser, os: block.os })
            : (block.browser ?? block.os),
        block.country,
    ]
        .filter(Boolean)
        .join(' · ');
</script>

<template>
    <Card class="overflow-hidden py-0">
        <CardContent class="p-0">
            <div class="flex items-start gap-3 p-5">
                <div class="bg-background text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg border">
                    <Ban class="size-5" />
                </div>
                <div class="space-y-0.5">
                    <p class="text-sm font-semibold">{{ t('workspace.security.blocked_visitors.title') }}</p>
                    <p class="text-muted-foreground text-sm">{{ t('workspace.security.blocked_visitors.help') }}</p>
                </div>
            </div>

            <ul v-if="blocks.length" class="divide-y border-t">
                <li v-for="block in blocks" :key="block.external_id" class="flex flex-wrap items-start justify-between gap-3 px-5 py-3">
                    <div class="min-w-0 space-y-0.5 text-sm">
                        <p class="flex flex-wrap items-center gap-2 font-medium">
                            {{ t('workspace.security.blocked_visitors.visitor', { id: block.visitor }) }}
                            <span v-if="device(block)" class="text-muted-foreground font-normal">{{ device(block) }}</span>
                        </p>
                        <p v-if="block.reason" class="text-muted-foreground break-words">{{ block.reason }}</p>
                        <p class="text-muted-foreground text-xs">
                            <template v-if="block.agent_name"
                                >{{ t('workspace.security.blocked_visitors.on_agent', { agent: block.agent_name }) }} ·
                            </template>
                            <template v-if="block.blocked_by"
                                >{{ t('workspace.security.blocked_visitors.by', { name: block.blocked_by }) }} ·
                            </template>
                            {{ formatDateTime(block.created_at) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary" class="font-normal">{{
                            block.expires_at
                                ? t('workspace.security.blocked_visitors.until', { date: formatDateTime(block.expires_at) })
                                : t('workspace.security.blocked_visitors.until_unblocked')
                        }}</Badge>
                        <Button v-if="canManage" variant="outline" size="sm" :disabled="removing === block.external_id" @click="unblock(block)">
                            <LoaderCircle v-if="removing === block.external_id" class="me-1.5 h-4 w-4 animate-spin" />
                            {{ t('workspace.security.blocked_visitors.unblock') }}
                        </Button>
                    </div>
                </li>
            </ul>
            <p v-else class="text-muted-foreground border-t px-5 py-4 text-sm">{{ t('workspace.security.blocked_visitors.empty') }}</p>
        </CardContent>
    </Card>
</template>
