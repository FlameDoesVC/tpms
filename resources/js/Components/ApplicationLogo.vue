<script setup>
import { computed } from 'vue';

const props = defineProps({
    size: { type: [String, Number], default: 32 },
});

const px = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size));
</script>

<template>
    <img src="/images/logo.png" alt="TPMS" :width="px" :height="px" class="object-contain" />
</template>
