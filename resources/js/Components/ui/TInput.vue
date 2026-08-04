<script setup>
import { computed, ref, useAttrs, useId } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    helper: { type: String, default: null },
    // The suffix is decoration by default, so it never steals a click meant for
    // the field. Set this when the suffix is a real control, e.g. the
    // show-password toggle on TPasswordInput.
    suffixInteractive: { type: Boolean, default: false },
});

const model = defineModel();
const attrs = useAttrs();
const inputRef = ref(null);

// Fall back to a generated id so the label is always click-targetable, even
// when the caller doesn't pass one.
const uid = useId();
const inputId = computed(() => attrs.id ?? `t-input-${uid}`);
const messageId = computed(() => `${inputId.value}-msg`);

// Callers reach for this on validation failure; without it they end up
// querying into the DOM for the inner input.
defineExpose({
    focus: () => inputRef.value?.focus(),
    select: () => inputRef.value?.select(),
    el: inputRef,
});
</script>

<template>
    <div>
        <label v-if="label" :for="inputId" class="mb-1.5 block text-sm font-medium text-foreground">{{ label }}</label>
        <div class="relative">
            <span v-if="$slots.prefix" class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-foreground-muted">
                <slot name="prefix" />
            </span>
            <input
                ref="inputRef"
                v-bind="$attrs"
                :id="inputId"
                v-model="model"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error || helper ? messageId : undefined"
                class="block w-full rounded-lg border bg-surface text-foreground shadow-sm transition-colors placeholder:text-foreground-muted focus:border-primary focus:ring-2 focus:ring-primary/20 sm:text-sm"
                :class="[
                    error ? 'border-danger' : '',
                    $slots.prefix ? 'pl-10' : '',
                    $slots.suffix ? 'pr-10' : '',
                ]"
            />
            <span
                v-if="$slots.suffix"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-foreground-muted"
                :class="suffixInteractive ? '' : 'pointer-events-none'"
            >
                <slot name="suffix" />
            </span>
        </div>
        <p v-if="error" :id="messageId" class="mt-1.5 text-sm text-danger">{{ error }}</p>
        <p v-else-if="helper" :id="messageId" class="mt-1.5 text-sm text-foreground-muted">{{ helper }}</p>
    </div>
</template>
