<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    size: { type: [String, Number], default: 32 },
});
</script>

<template>
    <ApplicationLogo :size="size" />
</template>
