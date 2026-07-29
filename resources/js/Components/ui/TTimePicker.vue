<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';

const props = defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    helper: { type: String, default: null },
    placeholder: { type: String, default: 'Select time' },
    // Full 1-minute fidelity by default so any value already stored on the
    // backend (however odd) is still representable, not just round numbers.
    minuteStep: { type: Number, default: 1 },
    clearable: { type: Boolean, default: false },
});

// 'HH:MM' 24-hour, same wire format as the native <input type="time"> it replaces.
const model = defineModel({ type: String, default: '' });

const open = ref(false);
const wrapperRef = ref(null);
const hourListRef = ref(null);
const minuteListRef = ref(null);

const pad = (n) => String(n).padStart(2, '0');
const hours = Array.from({ length: 24 }, (_, h) => pad(h));
const minutes = computed(() => {
    const out = [];
    for (let m = 0; m < 60; m += props.minuteStep) out.push(pad(m));
    // Keep the stored minute selectable even if it falls off the step grid.
    const current = model.value?.split(':')[1];
    if (current && !out.includes(current)) out.push(current);
    return out.sort();
});

const selectedHour = computed(() => model.value?.split(':')[0] ?? null);
const selectedMinute = computed(() => model.value?.split(':')[1] ?? null);

const displayLabel = computed(() => model.value || props.placeholder);

const setPart = (hour, minute) => {
    model.value = `${hour}:${minute}`;
};

const pickHour = (h) => setPart(h, selectedMinute.value ?? '00');
const pickMinute = (m) => setPart(selectedHour.value ?? '00', m);

const scrollIntoView = () => {
    nextTick(() => {
        hourListRef.value?.querySelector('[data-selected="true"]')?.scrollIntoView({ block: 'center' });
        minuteListRef.value?.querySelector('[data-selected="true"]')?.scrollIntoView({ block: 'center' });
    });
};

const openPicker = () => { open.value = true; scrollIntoView(); };
const close = () => { open.value = false; };
const toggle = () => (open.value ? close() : openPicker());

const setNow = () => {
    const d = new Date();
    model.value = `${pad(d.getHours())}:${pad(d.getMinutes() - (d.getMinutes() % (props.minuteStep || 1)))}`;
    scrollIntoView();
};

const clear = () => {
    model.value = '';
    close();
};

const onClickOutside = (e) => {
    if (wrapperRef.value && !wrapperRef.value.contains(e.target)) close();
};
onMounted(() => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside));

const onTriggerKeydown = (e) => {
    if (['Enter', ' ', 'ArrowDown'].includes(e.key)) {
        e.preventDefault();
        openPicker();
    }
};
const onPopoverKeydown = (e) => {
    if (e.key === 'Escape') { e.preventDefault(); close(); }
};
</script>

<template>
    <div ref="wrapperRef">
        <label v-if="label" class="mb-1.5 block text-sm font-medium text-foreground">{{ label }}</label>
        <div class="relative">
            <button
                type="button"
                class="flex w-full items-center gap-2 rounded-lg border bg-surface px-3 py-2 text-left text-sm shadow-xs transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                :class="[error ? 'border-danger' : '', clearable && model ? 'pr-8' : '']"
                @click="toggle"
                @keydown="onTriggerKeydown"
            >
                <span class="flex min-w-0 items-center gap-2 truncate [font-variant-numeric:tabular-nums]" :class="model ? 'text-foreground' : 'text-foreground-muted'">
                    <TIcon name="clock" :size="16" class="shrink-0 text-foreground-muted" />
                    {{ displayLabel }}
                </span>
            </button>
            <button
                v-if="clearable && model"
                type="button"
                class="absolute inset-y-0 right-2 my-auto h-fit shrink-0 rounded p-0.5 text-foreground-muted hover:bg-surface-hover hover:text-foreground"
                @click.stop="clear"
            >
                <TIcon name="x" :size="14" />
            </button>

            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="open"
                    class="elevated-lg absolute z-50 mt-1 w-44 origin-top rounded-lg border bg-surface p-2"
                    @keydown="onPopoverKeydown"
                >
                    <div class="flex gap-1">
                        <div class="flex-1">
                            <p class="mb-1 text-center text-[0.6875rem] font-semibold uppercase tracking-wide text-foreground-muted">Hour</p>
                            <div ref="hourListRef" class="h-40 overflow-y-auto rounded-md border bg-surface-sunken/40" role="listbox">
                                <button
                                    v-for="h in hours"
                                    :key="h"
                                    type="button"
                                    role="option"
                                    :aria-selected="h === selectedHour"
                                    :data-selected="h === selectedHour"
                                    class="block w-full py-1.5 text-center text-sm transition-colors [font-variant-numeric:tabular-nums]"
                                    :class="h === selectedHour ? 'bg-primary text-white font-medium' : 'text-foreground hover:bg-surface-hover'"
                                    @click="pickHour(h)"
                                >
                                    {{ h }}
                                </button>
                            </div>
                        </div>
                        <div class="flex-1">
                            <p class="mb-1 text-center text-[0.6875rem] font-semibold uppercase tracking-wide text-foreground-muted">Min</p>
                            <div ref="minuteListRef" class="h-40 overflow-y-auto rounded-md border bg-surface-sunken/40" role="listbox">
                                <button
                                    v-for="m in minutes"
                                    :key="m"
                                    type="button"
                                    role="option"
                                    :aria-selected="m === selectedMinute"
                                    :data-selected="m === selectedMinute"
                                    class="block w-full py-1.5 text-center text-sm transition-colors [font-variant-numeric:tabular-nums]"
                                    :class="m === selectedMinute ? 'bg-primary text-white font-medium' : 'text-foreground hover:bg-surface-hover'"
                                    @click="pickMinute(m)"
                                >
                                    {{ m }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 flex items-center justify-between border-t pt-2">
                        <button type="button" class="text-xs font-medium text-primary hover:underline" @click="setNow">Now</button>
                        <button type="button" class="text-xs text-foreground-muted hover:text-foreground hover:underline" @click="close">Done</button>
                    </div>
                </div>
            </Transition>
        </div>
        <p v-if="error" class="mt-1.5 text-sm text-danger">{{ error }}</p>
        <p v-else-if="helper" class="mt-1.5 text-sm text-foreground-muted">{{ helper }}</p>
    </div>
</template>
