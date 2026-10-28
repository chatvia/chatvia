<script setup lang="ts">
// What an invite dialog shows after the invitation went out: a confirmation
// (the default slot), the sign-up link to copy by hand, and buttons to invite
// someone else or close. Texts come from the caller's own i18n keys.
import CopyableLinkField from '@/dashboard/components/chatters/CopyableLinkField.vue';
import { Button } from '@/shared/ui/button';
import { DialogFooter } from '@/shared/ui/dialog';
import { Label } from '@/shared/ui/label';
import { Check, UserPlus } from 'lucide-vue-next';

defineProps<{
    acceptUrl: string;
    signupLinkLabel: string;
    expiresLabel: string;
    inviteAnotherLabel: string;
    doneLabel: string;
}>();

const emit = defineEmits<{
    another: [];
    done: [];
}>();
</script>

<template>
    <div class="space-y-4">
        <div
            class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-100"
        >
            <slot />
        </div>

        <div class="space-y-2">
            <Label>{{ signupLinkLabel }}</Label>
            <CopyableLinkField :url="acceptUrl" />
            <p class="text-muted-foreground text-xs">
                {{ expiresLabel }}
            </p>
        </div>

        <DialogFooter>
            <Button type="button" variant="outline" @click="emit('another')">
                <UserPlus class="me-2 h-4 w-4" />
                {{ inviteAnotherLabel }}
            </Button>
            <Button type="button" @click="emit('done')">
                <Check class="me-2 h-4 w-4" />
                {{ doneLabel }}
            </Button>
        </DialogFooter>
    </div>
</template>
