<script setup lang="ts">
// "Manage allowed IPs" dialog: an add/remove list of IP addresses or CIDR
// ranges. It edits a draft taken each time the dialog opens; "Done" writes the
// trimmed, de-duplicated list back, while closing any other way discards it.
import { Button } from '@/shared/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { Input } from '@/shared/ui/input';
import { Check, Network, Plus, Trash2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const open = defineModel<boolean>('open', { required: true });
const ips = defineModel<string[]>({ required: true });

const { t } = useI18n();

const draft = ref<string[]>([]);

watch(open, (isOpen) => {
    if (isOpen) draft.value = ips.value.length ? [...ips.value] : [''];
});

const addIp = () => draft.value.push('');
const removeIp = (index: number) => draft.value.splice(index, 1);

const apply = () => {
    ips.value = [...new Set(draft.value.map((v) => v.trim()).filter(Boolean))];
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('workspace.security.ip.dialog_title') }}</DialogTitle>
                <DialogDescription>{{ t('workspace.security.ip.dialog_description') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <div v-for="(ip, index) in draft" :key="index" class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <Network class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input
                            v-model="draft[index]"
                            :placeholder="t('workspace.security.ip.placeholder')"
                            class="ps-9 font-mono"
                            @keydown.enter.prevent="addIp"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-muted-foreground hover:text-destructive shrink-0"
                        :aria-label="t('workspace.security.ip.remove')"
                        @click="removeIp(index)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
                <p v-if="!draft.length" class="text-muted-foreground text-sm">{{ t('workspace.security.ip.empty_draft') }}</p>
            </div>

            <Button type="button" variant="outline" size="sm" class="w-fit" @click="addIp">
                <Plus class="me-2 h-4 w-4" />
                {{ t('workspace.security.ip.add') }}
            </Button>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="open = false">
                    <X class="me-2 h-4 w-4" />
                    {{ t('workspace.security.cancel') }}
                </Button>
                <Button type="button" @click="apply">
                    <Check class="me-2 h-4 w-4" />
                    {{ t('workspace.security.done') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
