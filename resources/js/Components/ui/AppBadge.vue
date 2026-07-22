<script setup>
defineProps({
    color: {
        type: String,
        default: 'gray',
    },
});

const colorClass = {
    gray: 'bg-gray-100 text-gray-800',
    green: 'bg-green-100 text-green-800',
    yellow: 'bg-yellow-100 text-yellow-800',
    red: 'bg-red-100 text-red-800',
    indigo: 'bg-indigo-100 text-indigo-800',
};
</script>

<template>
    <span
        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
        :class="colorClass[color] ?? colorClass.gray"
    >
        <slot />
    </span>
</template>
