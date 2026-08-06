<script setup>
import { computed, ref, useAttrs, useId, watch } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    helper: { type: String, default: null },
    min: { type: Number, default: null },
    max: { type: Number, default: null },
    step: { type: Number, default: 1 },
    size: { type: String, default: 'md' },
    // A quantity stepper reads badly stretched full-bleed (buttons flung to
    // opposite edges with dead space between) — unlike a text field, its
    // value is always short, so it defaults to a compact fixed width rather
    // than filling whatever row/column it lands in. Bound via inline style
    // (not a `w-*` class) so it can't lose a specificity fight with a class
    // a caller passes on the component tag; pass `width` to override, or
    // `full` to explicitly opt back into filling the container.
    width: { type: String, default: null },
    // 'top' stacks the label above the stepper, the natural fit inside a
    // form. 'left' sits it beside the stepper on one line instead — for a
    // stepper placed next to a single-line control like a button, stacking
    // makes the stepper's own baseline sit lower than the button beside it
    // even under `items-center`, since the whole label+box block is what
    // gets centered, not the box alone.
    labelPosition: { type: String, default: 'top' },
});

const model = defineModel({ type: Number, default: null });
const attrs = useAttrs();

const uid = useId();
const inputId = computed(() => attrs.id ?? `t-number-${uid}`);
const isDisabled = computed(() => attrs.disabled === '' || !!attrs.disabled);

// Decimal places come from the step itself (0.01 -> 2dp), so price fields
// round cleanly instead of accumulating floating-point noise like 19.9999999.
const precision = computed(() => {
    const s = String(props.step);
    const i = s.indexOf('.');
    return i === -1 ? 0 : s.length - i - 1;
});
const round = (n) => Number(n.toFixed(precision.value));
const clamp = (n) => {
    let v = n;
    if (props.min != null) v = Math.max(props.min, v);
    if (props.max != null) v = Math.min(props.max, v);
    return v;
};

// A separate text buffer, not bound straight to the model: typing "1" toward
// "12" would otherwise get clamped/rounded mid-keystroke and fight the user.
const raw = ref(model.value == null ? '' : String(model.value));
watch(model, (v) => {
    const next = v == null ? '' : String(v);
    if (next !== raw.value) raw.value = next;
});

const atMin = computed(() => props.min != null && (model.value ?? 0) <= props.min);
const atMax = computed(() => props.max != null && (model.value ?? 0) >= props.max);

const increment = () => {
    const next = clamp(round((model.value ?? props.min ?? 0) + props.step));
    model.value = next;
    raw.value = String(next);
};
const decrement = () => {
    const next = clamp(round((model.value ?? props.min ?? 0) - props.step));
    model.value = next;
    raw.value = String(next);
};

const onInput = (e) => {
    const v = e.target.value;
    const pattern = precision.value > 0 ? /^-?\d*\.?\d*$/ : /^-?\d*$/;
    if (!pattern.test(v)) {
        e.target.value = raw.value;
        return;
    }
    raw.value = v;
    const n = parseFloat(v);
    if (!Number.isNaN(n)) model.value = n;
};

const onBlur = () => {
    let n = parseFloat(raw.value);
    if (Number.isNaN(n)) n = props.min ?? 0;
    n = clamp(round(n));
    model.value = n;
    raw.value = String(n);
};

const onKeydown = (e) => {
    if (e.key === 'ArrowUp') { e.preventDefault(); increment(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); decrement(); }
};

const sizeClasses = {
    sm: { input: 'h-7 text-xs', btn: 'w-6', defaultWidth: '6.5rem' },
    // h-[2.375rem] (38px), not h-10: every text field in the system - TInput,
    // TSelect, TDatePicker's trigger - lands at 38px (py-2 + text-sm + border),
    // and the stepper sitting 2px taller made any row pairing them ragged.
    md: { input: 'h-[2.375rem] text-sm', btn: 'w-9', defaultWidth: '8.5rem' },
};
const cls = computed(() => sizeClasses[props.size] ?? sizeClasses.md);
const boxStyle = computed(() => (props.width === 'full' ? {} : { width: props.width ?? cls.value.defaultWidth }));
</script>

<template>
    <div>
        <div :class="labelPosition === 'left' ? 'flex items-center gap-2' : ''">
            <label
                v-if="label"
                :for="inputId"
                class="text-sm font-medium text-foreground"
                :class="labelPosition === 'left' ? 'shrink-0 whitespace-nowrap' : 'mb-1.5 block'"
            >
                {{ label }}
            </label>
            <div
                class="flex items-stretch overflow-hidden rounded-lg border bg-surface shadow-xs transition-colors focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
                :class="[error ? 'border-danger' : '', props.width === 'full' ? 'w-full' : '']"
                :style="boxStyle"
            >
            <button
                type="button"
                tabindex="-1"
                :disabled="atMin || isDisabled"
                class="grid shrink-0 place-items-center border-r text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                :class="cls.btn"
                @click="decrement"
            >
                <TIcon name="minus" :size="14" />
            </button>
            <input
                v-bind="attrs"
                :id="inputId"
                type="text"
                inputmode="decimal"
                :value="raw"
                :aria-invalid="error ? 'true' : undefined"
                class="w-full min-w-0 border-0 bg-transparent text-center text-foreground focus:outline-none focus:ring-0"
                :class="cls.input"
                @input="onInput"
                @blur="onBlur"
                @keydown="onKeydown"
            />
            <button
                type="button"
                tabindex="-1"
                :disabled="atMax || isDisabled"
                class="grid shrink-0 place-items-center border-l text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground disabled:pointer-events-none disabled:opacity-40"
                :class="cls.btn"
                @click="increment"
            >
                <TIcon name="plus" :size="14" />
            </button>
            </div>
        </div>
        <p v-if="error" class="mt-1.5 text-sm text-danger">{{ error }}</p>
        <p v-else-if="helper" class="mt-1.5 text-sm text-foreground-muted">{{ helper }}</p>
    </div>
</template>
