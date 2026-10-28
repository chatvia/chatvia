<script setup lang="ts">
/** "What are Topics?" — static help about how topic analysis works. */
import { Button } from '@/shared/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { useI18n } from 'vue-i18n';

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('agents.topics_index.info_title') }}</DialogTitle>
                <DialogDescription>{{ t('agents.topics_index.info_description') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-4 text-sm">
                <div>
                    <h3 class="mb-2 font-semibold">{{ t('agents.topics_index.info_overview_title') }}</h3>
                    <p class="text-muted-foreground">
                        {{ t('agents.topics_index.info_overview') }}
                    </p>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold">{{ t('agents.topics_index.info_how_title') }}</h3>
                    <ul class="text-muted-foreground list-inside list-disc space-y-2">
                        <li>{{ t('agents.topics_index.info_how_1') }}</li>
                        <li>{{ t('agents.topics_index.info_how_2') }}</li>
                        <li>{{ t('agents.topics_index.info_how_3') }}</li>
                        <li>{{ t('agents.topics_index.info_how_4') }}</li>
                    </ul>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold">{{ t('agents.topics_index.info_examples_title') }}</h3>
                    <div class="text-muted-foreground space-y-2">
                        <div class="flex items-start gap-2">
                            <span class="text-primary">•</span>
                            <span>{{ t('agents.topics_index.info_example_1') }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-primary">•</span>
                            <span>{{ t('agents.topics_index.info_example_2') }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-primary">•</span>
                            <span>{{ t('agents.topics_index.info_example_3') }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold">{{ t('agents.topics_index.info_benefits_title') }}</h3>
                    <ul class="text-muted-foreground list-inside list-disc space-y-2">
                        <li>{{ t('agents.topics_index.info_benefits_1') }}</li>
                        <li>{{ t('agents.topics_index.info_benefits_2') }}</li>
                        <li>{{ t('agents.topics_index.info_benefits_3') }}</li>
                        <li>{{ t('agents.topics_index.info_benefits_4') }}</li>
                    </ul>
                </div>
            </div>

            <DialogFooter>
                <Button @click="open = false">{{ t('agents.topics_index.got_it') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
