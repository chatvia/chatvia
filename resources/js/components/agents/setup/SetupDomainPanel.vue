<script setup lang="ts">
/**
 * Setup → Domain (Hosted Chat only): serve the hosted chat on the workspace's
 * own hostname through a CNAME record: shows the DNS record to create and the
 * hostname input.
 */
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import SetupSectionHeading from '@/components/agents/setup/SetupSectionHeading.vue';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';
import type { SetupDomainContext } from '@/composables/agents/setup/useSetupDomain';
import { Check, Copy, Save } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    domain: SetupDomainContext;
}>();

const { t } = useI18n();
const { locked, domain: section } = useSetupContext();
const { form, isSaving, save, clear, isCnameCopied, copyCnameTarget } = section;
</script>

<template>
    <div class="space-y-6 p-6">
        <SetupSectionHeading
            :title="t('agents.setup.domain.title')"
            :description="t('agents.setup.domain.description', { target: domain.cname_target })"
        />

        <!-- CNAME instructions -->
        <div class="space-y-3">
            <Label class="text-xs">{{ t('agents.setup.domain.step1') }}</Label>
            <div class="bg-muted overflow-x-auto rounded-lg border p-4 font-mono text-xs">
                <div class="grid grid-cols-[80px_1fr] gap-x-4 gap-y-1">
                    <span class="text-muted-foreground">{{ t('agents.setup.domain.type') }}</span
                    ><span class="font-semibold">CNAME</span> <span class="text-muted-foreground">{{ t('agents.setup.domain.name') }}</span
                    ><span>{{ t('agents.setup.domain.cname_name') }}</span>
                    <span class="text-muted-foreground">{{ t('agents.setup.domain.value') }}</span>
                    <span class="flex items-center gap-2">
                        <code class="font-semibold">{{ domain.cname_target }}</code>
                        <Button type="button" variant="ghost" size="sm" class="h-6 gap-1 px-2" @click="copyCnameTarget">
                            <component :is="isCnameCopied ? Check : Copy" class="h-3 w-3" />
                            {{ isCnameCopied ? t('agents.setup.copied') : t('workspace.common.copy') }}
                        </Button>
                    </span>
                </div>
            </div>
            <p class="text-muted-foreground text-xs">
                {{ t('agents.setup.domain.dns_hint') }}
            </p>
        </div>

        <!-- Domain input -->
        <div class="space-y-2">
            <Label for="custom-domain" class="text-xs">{{ t('agents.setup.domain.step2') }}</Label>
            <div class="flex items-start gap-2">
                <div class="flex-1">
                    <Input
                        id="custom-domain"
                        v-model="form.domain"
                        type="text"
                        :placeholder="t('agents.setup.domain.placeholder')"
                        autocomplete="off"
                        :disabled="locked"
                    />
                    <p v-if="form.errors.domain" class="text-destructive mt-1 text-xs">
                        {{ form.errors.domain }}
                    </p>
                </div>
                <Button type="button" size="sm" :disabled="locked || isSaving || form.domain === (domain.value ?? '')" @click="save">
                    <Save class="me-2 h-4 w-4" />
                    {{ isSaving ? t('agents.setup.saving') : t('common.save') }}
                </Button>
                <Button
                    v-if="domain.value"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-destructive"
                    :disabled="locked || isSaving"
                    @click="clear"
                >
                    {{ t('common.remove') }}
                </Button>
            </div>
            <p class="text-muted-foreground text-xs">
                {{ t('agents.setup.domain.hostname_hint') }}
            </p>
        </div>
    </div>
</template>
