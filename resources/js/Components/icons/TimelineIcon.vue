<script setup>
defineProps({
    type: {
        type: String,
        required: true, // 'hotel' | 'ferry-out' | 'ferry-back' | 'themepark'
    },
});
</script>

<template>
    <svg v-if="type === 'hotel'" viewBox="0 0 24 24" fill="none" class="h-4 w-4 shrink-0">
        <rect x="5" y="3" width="14" height="18" rx="1" stroke="currentColor" stroke-width="1.5" />
        <rect x="8" y="6" width="2" height="2" fill="currentColor" />
        <rect x="14" y="6" width="2" height="2" fill="currentColor" />
        <rect x="8" y="10" width="2" height="2" fill="currentColor" />
        <rect x="14" y="10" width="2" height="2" fill="currentColor" />
        <rect x="10" y="15" width="4" height="6" stroke="currentColor" stroke-width="1.5" />
    </svg>

    <svg v-else-if="type === 'ferry-out'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 shrink-0">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 12h16m-6-6l6 6-6 6" />
    </svg>

    <svg v-else-if="type === 'ferry-back'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 shrink-0">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4m6-6l-6 6 6 6" />
    </svg>

    <svg v-else viewBox="0 0 24 24" fill="none" class="h-4 w-4 shrink-0">
        <circle cx="12" cy="9" r="6" stroke="currentColor" stroke-width="1.5" />
        <rect x="11" y="14" width="2" height="7" fill="currentColor" />
    </svg>
</template>
