<script setup lang="ts">
/**
 * The Hosted Chat's custom domain on the Deploy page (help.yourshop.com):
 * save one, see the CNAME record to add and where the domain stands, check
 * it again or remove it. It needs a license key and the admin's Custom
 * domains switch; the server refuses the actions without them too.
 */
import DeployCopyButton from '@/components/agents/deploy/DeployCopyButton.vue';
import LicenseNotice from '@/components/license/LicenseNotice.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSnippetClipboard } from '@/composables/agents/deploy/useSnippetClipboard';
import { destroy as destroyDomain, update as updateDomain } from '@/routes/agents/domain';
import { store as checkDomain } from '@/routes/agents/domain/checks';
import { router, useForm } from '@inertiajs/vue3';
import { Globe, Info, RefreshCw, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agentId: string;
    domain: Domain.Agent.Data.Resources.Account.AgentDomainResourceData | null;
    options: Domain.Agent.Data.Resources.Account.CustomDomainOptionsData;
}>();

const { t } = useI18n();
const { copiedKey, copySnippet } = useSnippetClipboard();

const form = useForm<{ host: string }>({ host: props.domain?.host ?? '' });
const isBusy = ref(false);

const statusVariant = computed(() => {
    switch (props.domain?.status) {
        case 'active':
            return 'default';
        case 'problem':
            return 'destructive';
        default:
            return 'secondary';
    }
});

// The last check explains a domain that does not work yet.
const problemText = computed(() => {
    const check = props.domain?.last_check;

    if (props.domain?.status === 'active' || !check || check === 'reached') {
        return null;
    }

    return t(`agents.deploy.custom_domain.check.${check}`, { target: props.options.cname_target });
});

function save(): void {
    form.put(updateDomain.url(props.agentId), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('agents.deploy.custom_domain.saved')),
    });
}

function checkAgain(): void {
    isBusy.value = true;
    router.post(
        checkDomain.url(props.agentId),
        {},
        {
            preserveScroll: true,
            onFinish: () => (isBusy.value = false),
        },
    );
}

function remove(): void {
    isBusy.value = true;
    router.delete(destroyDomain.url(props.agentId), {
        preserveScroll: true,
        onSuccess: () => {
            form.host = '';
            toast.success(t('agents.deploy.custom_domain.removed'));
        },
        onFinish: () => (isBusy.value = false),
    });
}
</script>

<template>
    <div class="space-y-3 border-t pt-4">
        <div class="space-y-1">
            <Label for="deploy-custom-domain" class="text-sm font-medium">{{ t('agents.deploy.custom_domain.title') }}</Label>
            <p class="text-muted-foreground text-xs">{{ t('agents.deploy.custom_domain.description') }}</p>
        </div>

        <LicenseNotice v-if="!options.is_licensed" />

        <Alert v-else-if="!options.is_enabled">
            <Info />
            <AlertTitle>{{ t('agents.deploy.custom_domain.off_title') }}</AlertTitle>
            <AlertDescription>{{ t('agents.deploy.custom_domain.off_body') }}</AlertDescription>
        </Alert>

        <template v-else>
            <form class="flex gap-2" @submit.prevent="save">
                <div class="relative flex-1">
                    <Globe class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                    <Input
                        id="deploy-custom-domain"
                        v-model="form.host"
                        dir="ltr"
                        class="ps-9"
                        autocomplete="off"
                        :placeholder="t('agents.deploy.custom_domain.placeholder')"
                        :error="form.errors.host"
                    />
                </div>
                <Button type="submit" size="sm" class="h-9 gap-2" :disabled="form.processing || !form.host">
                    <Save class="size-4" /> {{ t('agents.deploy.save') }}
                </Button>
            </form>
            <p v-if="form.errors.host" class="text-destructive text-sm">{{ form.errors.host }}</p>

            <div v-if="domain" class="space-y-3 rounded-lg border p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <code class="text-sm" dir="ltr">{{ domain.host }}</code>
                        <Badge :variant="statusVariant">{{ t(`agents.deploy.custom_domain.status.${domain.status}`) }}</Badge>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button type="button" variant="outline" size="sm" class="gap-2" :disabled="isBusy" @click="checkAgain">
                            <RefreshCw class="size-4" :class="{ 'animate-spin': isBusy }" /> {{ t('agents.deploy.custom_domain.check_again') }}
                        </Button>
                        <Button type="button" variant="outline" size="sm" class="gap-2" :disabled="isBusy" @click="remove">
                            <Trash2 class="size-4" /> {{ t('agents.deploy.custom_domain.remove') }}
                        </Button>
                    </div>
                </div>

                <p v-if="domain.status === 'active'" class="text-muted-foreground text-sm">
                    {{ t('agents.deploy.custom_domain.active_body') }}
                </p>
                <template v-else>
                    <p class="text-sm" :class="problemText ? 'text-destructive' : 'text-muted-foreground'">
                        {{ problemText ?? t('agents.deploy.custom_domain.waiting_body') }}
                    </p>

                    <div class="space-y-2">
                        <p class="text-sm font-medium">{{ t('agents.deploy.custom_domain.dns_title') }}</p>
                        <dl class="bg-muted grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-lg p-3 text-sm">
                            <dt class="text-muted-foreground">{{ t('agents.deploy.custom_domain.dns_type') }}</dt>
                            <dd><code dir="ltr">CNAME</code></dd>
                            <dt class="text-muted-foreground">{{ t('agents.deploy.custom_domain.dns_name') }}</dt>
                            <dd>
                                <code dir="ltr">{{ domain.host }}</code>
                            </dd>
                            <dt class="text-muted-foreground">{{ t('agents.deploy.custom_domain.dns_value') }}</dt>
                            <dd class="flex items-center justify-between gap-2">
                                <code dir="ltr" class="truncate">{{ options.cname_target }}</code>
                                <DeployCopyButton :copied="copiedKey === 'cname'" @click="copySnippet(options.cname_target, 'cname')" />
                            </dd>
                        </dl>
                    </div>
                </template>
            </div>
        </template>
    </div>
</template>
