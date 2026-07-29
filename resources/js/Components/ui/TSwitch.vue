<script setup>
defineProps({
    label: { type: String, default: null },
    description: { type: String, default: null },
});

const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <label class="inline-flex cursor-pointer items-center gap-3">
        <button
            type="button"
            role="switch"
            :aria-checked="model"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:ring-offset-2"
            :class="model ? 'bg-primary' : 'bg-foreground-muted/40'"
            @click="model = !model"
        >
            <span
                class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-sm ring-0 transition-transform duration-200"
                :class="model ? 'translate-x-5' : 'translate-x-0'"
            />
        </button>
        <span v-if="label || description" class="flex flex-col">
            <span v-if="label" class="text-sm font-medium text-foreground">{{ label }}</span>
            <span v-if="description" class="text-sm text-foreground-muted">{{ description }}</span>
        </span>
    </label>
</template>
