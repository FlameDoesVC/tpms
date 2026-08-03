<script setup>
import TConfirmDialog from '@/Components/ui/TConfirmDialog.vue';
import { useConfirmState } from '@/composables/useConfirm';

// Mounted once in App.vue; every useConfirm() call anywhere in the app drives
// this instance.
const { state, settle } = useConfirmState();
</script>

<template>
    <TConfirmDialog
        :show="state.open"
        :title="state.options.title"
        :message="state.options.message"
        :confirm-label="state.options.confirmLabel"
        :cancel-label="state.options.cancelLabel"
        :danger="state.options.danger"
        @confirm="settle(true)"
        @cancel="settle(false)"
    />
</template>
