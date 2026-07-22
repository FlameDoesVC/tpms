<script setup>
defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    variant: {
        type: String,
        default: 'primary',
    },
});

const variantClass = {
    primary: 'bg-gray-800 text-white hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900',
    danger: 'bg-red-600 text-white hover:bg-red-500 focus:bg-red-500 active:bg-red-700',
    secondary: 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:bg-gray-50',
};
</script>

<template>
    <button
        v-bind="$attrs"
        :disabled="loading || $attrs.disabled"
        class="inline-flex items-center justify-center gap-2 rounded-md border border-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        :class="variantClass[variant]"
    >
        <svg
            v-if="loading"
            class="h-4 w-4 animate-spin"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <slot />
    </button>
</template>
