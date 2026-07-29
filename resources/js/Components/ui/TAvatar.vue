<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    size: { type: String, default: 'md' },
});

const initials = computed(() => {
    return props.name
        .split(' ')
        .map(w => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

const sizeClasses = {
    sm: 'h-7 w-7 text-xs',
    md: 'h-9 w-9 text-sm',
    lg: 'h-11 w-11 text-base',
};

const bgColor = computed(() => {
    let hash = 0;
    for (const c of props.name) hash = c.charCodeAt(0) + ((hash << 5) - hash);
    const hue = Math.abs(hash) % 360;
    return `hsl(${hue}, 45%, 55%)`;
});
</script>

<template>
    <span
        class="inline-flex items-center justify-center rounded-full font-medium text-white"
        :class="sizeClasses[size]"
        :style="{ backgroundColor: bgColor }"
    >
        {{ initials }}
    </span>
</template>
