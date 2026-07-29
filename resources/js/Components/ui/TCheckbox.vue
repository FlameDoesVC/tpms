<script setup>
defineProps({
    label: { type: String, default: null },
    description: { type: String, default: null },
});

const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <label class="inline-flex cursor-pointer items-start gap-3">
        <input
            type="checkbox"
            v-model="model"
            v-bind="$attrs"
            class="mt-0.5 h-4 w-4 rounded border-strong bg-surface text-primary transition-colors focus:ring-2 focus:ring-primary/20"
        />
        <span v-if="label || description" class="flex flex-col">
            <span v-if="label" class="text-sm font-medium text-foreground">{{ label }}</span>
            <span v-if="description" class="text-sm text-foreground-muted">{{ description }}</span>
        </span>
    </label>
</template>
