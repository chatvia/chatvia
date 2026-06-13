<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useI18n } from 'vue-i18n';

interface Props {
  title?: string;
  description?: string;
  confirmText?: string;
  cancelText?: string;
  open?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  open: false
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'confirm'): void;
  (e: 'cancel'): void;
}>();

const { t } = useI18n();

const displayTitle = computed(() => props.title ?? t('shared.delete_confirmation_title'));
const displayDescription = computed(() => props.description ?? t('shared.delete_confirmation_description'));
const displayConfirmText = computed(() => props.confirmText ?? t('shared.yes_delete'));
const displayCancelText = computed(() => props.cancelText ?? t('common.cancel'));

const isOpen = ref(props.open);

const confirm = () => {
  emit('confirm');
  isOpen.value = false;
  emit('update:open', false);
};

const cancel = () => {
  emit('cancel');
  isOpen.value = false;
  emit('update:open', false);
};

// Watch for changes in the open prop
watch(() => props.open, (newValue) => {
  isOpen.value = newValue;
});

// Emit changes in the isOpen ref
watch(isOpen, (newValue) => {
  emit('update:open', newValue);
});
</script>

<template>
  <Dialog v-model:open="isOpen">
    <DialogTrigger as-child>
      <slot name="trigger"></slot>
    </DialogTrigger>
    <DialogContent>
      <DialogHeader>
        <DialogTitle>{{ displayTitle }}</DialogTitle>
        <DialogDescription>
          {{ displayDescription }}
        </DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" @click="cancel">
          {{ displayCancelText }}
        </Button>
        <Button variant="destructive" @click="confirm">
          {{ displayConfirmText }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
