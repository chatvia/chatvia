<script setup lang="ts">
// "Create team" dialog shared by the Teams page and the header team switcher.
// Emits `created` after the team is stored so the opener can refresh its list.
import { useForm } from '@inertiajs/vue3';
import { Loader2, Plus, Users } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ created: [] }>();

const { t } = useI18n();

const createForm = useForm({
    name: '',
});

const createTeam = () => {
    createForm.post(route('teams.store'), {
        onSuccess: () => {
            open.value = false;
            createForm.reset();
            emit('created');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('teams.index.create_dialog.title') }}</DialogTitle>
                <DialogDescription>{{ t('teams.index.create_dialog.description') }}</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="createTeam">
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="team-name">{{ t('teams.index.create_dialog.name_label') }}</Label>
                        <div class="relative">
                            <Users class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                            <Input
                                id="team-name"
                                v-model="createForm.name"
                                :placeholder="t('teams.index.create_dialog.name_placeholder')"
                                class="ps-9"
                                required
                            />
                        </div>
                        <InputError :message="createForm.errors.name" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">{{ t('teams.common.cancel') }}</Button>
                    <Button type="submit" :disabled="createForm.processing">
                        <Loader2 v-if="createForm.processing" class="me-2 size-4 animate-spin" />
                        <Plus v-else class="me-2 size-4" />
                        {{ t('teams.index.create_team') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
