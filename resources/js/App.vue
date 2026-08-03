<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import ToastNotification from '@/Components/ToastNotification.vue';
import ConfirmDialogHost from '@/Components/ConfirmDialogHost.vue';

const auth = useAuthStore();

onMounted(() => {
    auth.fetchUser();
});
</script>

<template>
    <router-view />
    <ToastNotification />
    <ConfirmDialogHost />
</template>
