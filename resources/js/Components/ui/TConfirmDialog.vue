<script setup>
import { computed } from 'vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    danger: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

// TModal owns a writable `show` model and clears it on Escape, backdrop click
// and the close button. Bridging through a computed turns every one of those
// dismissals into an explicit cancel, so the pending promise always settles
// instead of the dialog just disappearing.
const modalShow = computed({
    get: () => props.show,
    set: (value) => {
        if (!value) emit('cancel');
    },
});
</script>

<template>
    <TModal v-model:show="modalShow" max-width="sm">
        <template #title>{{ title }}</template>

        <p class="text-sm text-foreground-secondary">{{ message }}</p>

        <template #footer>
            <TButton variant="secondary" @click="emit('cancel')">{{ cancelLabel }}</TButton>
            <TButton :variant="danger ? 'danger' : 'primary'" @click="emit('confirm')">
                {{ confirmLabel }}
            </TButton>
        </template>
    </TModal>
</template>
