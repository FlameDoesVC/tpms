<script setup>
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

onMounted(() => {
    auth.fetchUser();
});
</script>

<template>
    <router-view />
</template>
