<script setup>
import { computed, useAttrs, useId } from 'vue';

// Without this, attrs land on the root <label> automatically AND again on the
// input via the explicit v-bind - so a passed class or id was applied twice.
defineOptions({ inheritAttrs: false });

defineProps({
    label: { type: String, default: null },
    description: { type: String, default: null },
});

const model = defineModel({ type: Boolean, default: false });
const attrs = useAttrs();

const uid = useId();
const inputId = computed(() => attrs.id ?? `t-checkbox-${uid}`);

// class/style belong on the wrapper; everything else belongs on the control.
const controlAttrs = computed(() => {
    const { class: _c, style: _s, ...rest } = attrs;
    return rest;
});
</script>

<template>
    <label :class="$attrs.class" :style="$attrs.style" class="inline-flex cursor-pointer items-start gap-3">
        <input
            v-bind="controlAttrs"
            :id="inputId"
            v-model="model"
            type="checkbox"
            class="mt-0.5 h-4 w-4 shrink-0 rounded border-strong bg-surface text-primary transition-colors focus:ring-2 focus:ring-primary/20"
        />
        <span v-if="label || description" class="flex min-w-0 flex-col">
            <span v-if="label" class="truncate text-sm font-medium text-foreground">{{ label }}</span>
            <span v-if="description" class="truncate text-sm text-foreground-muted">{{ description }}</span>
        </span>
    </label>
</template>
