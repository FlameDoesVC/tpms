<script setup>
defineProps({
    label: { type: String, default: null },
    options: { type: Array, default: () => [] },
    layout: { type: String, default: 'vertical' },
    error: { type: String, default: null },
});

const model = defineModel();
</script>

<template>
    <fieldset>
        <legend v-if="label" class="mb-2 text-sm font-medium text-foreground">{{ label }}</legend>
        <div :class="layout === 'horizontal' ? 'flex flex-wrap gap-4' : 'space-y-2'">
            <label
                v-for="option in options"
                :key="option.value"
                class="flex cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 transition-colors"
                :class="model === option.value ? 'border-primary bg-primary-soft' : 'border-transparent hover:bg-surface-hover'"
            >
                <input
                    type="radio"
                    :value="option.value"
                    v-model="model"
                    class="mt-0.5 h-4 w-4 border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                />
                <span class="flex flex-col">
                    <span class="text-sm font-medium text-foreground">{{ option.label }}</span>
                    <span v-if="option.description" class="text-sm text-foreground-muted">{{ option.description }}</span>
                </span>
            </label>
        </div>
        <p v-if="error" class="mt-1.5 text-sm text-danger">{{ error }}</p>
    </fieldset>
</template>
