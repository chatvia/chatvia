<script lang="ts" setup>
/**
 * The Inbox: everyone chatting with the workspace's agents right now, on one
 * page. The list sits on the start side and the open chat beside it; on a
 * phone the list and the chat take turns.
 */
import AppLayout from '@/layouts/AppLayout.vue';
import { inbox as inboxPage } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { provide } from 'vue';
import { useI18n } from 'vue-i18n';
import InboxList from './InboxList.vue';
import InboxThread from './InboxThread.vue';
import { inboxKey, useInbox } from './useInbox';

const { t } = useI18n();

const inbox = useInbox();
provide(inboxKey, inbox);

const { selectedId, select } = inbox;

const breadcrumbs: BreadcrumbItem[] = [{ title: t('inbox.title'), href: inboxPage.url() }];
</script>

<template>
    <Head :title="t('inbox.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="bg-background flex h-[calc(100vh-8rem)] min-h-[28rem] overflow-hidden rounded-xl border">
            <aside class="w-full shrink-0 border-e lg:w-80" :class="selectedId ? 'hidden lg:block' : 'block'">
                <InboxList />
            </aside>
            <section class="min-w-0 flex-1" :class="selectedId ? 'block' : 'hidden lg:block'">
                <InboxThread @back="select(null)" />
            </section>
        </div>
    </AppLayout>
</template>
