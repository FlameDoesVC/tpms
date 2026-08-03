<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TMonthYearPanel from '@/Components/ui/TMonthYearPanel.vue';

const props = defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    helper: { type: String, default: null },
    placeholder: { type: String, default: 'Select date' },
    // ISO 'YYYY-MM-DD' strings. Fixed-width format means plain string
    // comparison against a day cell's iso is enough to decide disabled state.
    min: { type: String, default: null },
    max: { type: String, default: null },
    clearable: { type: Boolean, default: false },
});

const model = defineModel({ type: String, default: '' });

const open = ref(false);
const wrapperRef = ref(null);
const gridRef = ref(null);
const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const toIso = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};
const todayIso = toIso(new Date());

// Local-midnight parse avoids the day-before shift that `new Date(iso)`
// causes by reading the bare date as UTC.
const parseIso = (iso) => new Date(`${iso || todayIso}T00:00:00`);

const viewDate = ref(parseIso(model.value));

// Swaps the day grid for the month/year jump, rather than opening a second
// popover inside this one - a 17rem panel nested in a 18rem popover would spill
// out of it. Same reason a native picker drills in place.
const jumping = ref(false);
const viewIso = computed(() => toIso(viewDate.value));

const onJump = (iso) => {
    viewDate.value = parseIso(iso);
};

const displayLabel = computed(() => {
    if (!model.value) return props.placeholder;
    return parseIso(model.value).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
});

const monthLabel = computed(() => `${MONTHS[viewDate.value.getMonth()]} ${viewDate.value.getFullYear()}`);

const weeks = computed(() => {
    const year = viewDate.value.getFullYear();
    const month = viewDate.value.getMonth();
    const firstOfMonth = new Date(year, month, 1);
    const gridStart = new Date(year, month, 1 - firstOfMonth.getDay());

    const days = [];
    for (let i = 0; i < 42; i++) {
        const d = new Date(gridStart);
        d.setDate(gridStart.getDate() + i);
        const iso = toIso(d);
        days.push({
            iso,
            day: d.getDate(),
            inMonth: d.getMonth() === month,
            isToday: iso === todayIso,
            isSelected: iso === model.value,
            disabled: (props.min && iso < props.min) || (props.max && iso > props.max),
        });
    }
    const rows = [];
    for (let i = 0; i < days.length; i += 7) rows.push(days.slice(i, i + 7));
    return rows;
});

const openPicker = () => {
    viewDate.value = parseIso(model.value);
    // Always opens on the day grid; the jump view is somewhere you go, not a
    // state the field remembers.
    jumping.value = false;
    open.value = true;
    nextTick(() => {
        const el = gridRef.value?.querySelector('[data-selected="true"]') ?? gridRef.value?.querySelector('[data-today="true"]');
        el?.focus();
    });
};
const close = () => { open.value = false; };
const toggle = () => (open.value ? close() : openPicker());

const changeMonth = (delta) => {
    const d = new Date(viewDate.value);
    d.setMonth(d.getMonth() + delta);
    viewDate.value = d;
};

const select = (day) => {
    if (day.disabled) return;
    model.value = day.iso;
    close();
};

const selectToday = () => {
    if (props.min && todayIso < props.min) return;
    if (props.max && todayIso > props.max) return;
    model.value = todayIso;
    close();
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
const onGridKeydown = (e) => {
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
                <span class="flex min-w-0 items-center gap-2 truncate" :class="model ? 'text-foreground' : 'text-foreground-muted'">
                    <TIcon name="calendar" :size="16" class="shrink-0 text-foreground-muted" />
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
                    class="elevated-lg absolute z-50 mt-1 w-72 origin-top rounded-lg border bg-surface p-3"
                    @keydown="onGridKeydown"
                >
                    <TMonthYearPanel
                        v-if="jumping"
                        :model-value="viewIso"
                        class="!w-full !p-0"
                        @update:model-value="onJump"
                        @close="jumping = false"
                    />

                    <template v-else>
                    <div class="mb-2 flex items-center justify-between">
                        <button type="button" class="rounded p-1.5 text-foreground-muted hover:bg-surface-hover hover:text-foreground" aria-label="Previous month" @click="changeMonth(-1)">
                            <TIcon name="chevronLeft" :size="16" />
                        </button>
                        <!-- Clicking the month opens the jump view in place, so a
                             date two quarters out isn't six presses of Next. -->
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded px-2 py-1 text-sm font-semibold text-foreground hover:bg-surface-hover"
                            aria-label="Jump to another month or year"
                            @click="jumping = true"
                        >
                            {{ monthLabel }}
                            <TIcon name="chevronDown" :size="13" class="text-foreground-muted" />
                        </button>
                        <button type="button" class="rounded p-1.5 text-foreground-muted hover:bg-surface-hover hover:text-foreground" aria-label="Next month" @click="changeMonth(1)">
                            <TIcon name="chevronRight" :size="16" />
                        </button>
                    </div>

                    <div class="grid grid-cols-7 text-center text-xs font-medium text-foreground-muted">
                        <span v-for="wd in WEEKDAYS" :key="wd" class="py-1">{{ wd }}</span>
                    </div>

                    <div ref="gridRef" class="grid grid-cols-7 gap-y-0.5">
                        <button
                            v-for="day in weeks.flat()"
                            :key="day.iso"
                            type="button"
                            :disabled="day.disabled"
                            :data-selected="day.isSelected"
                            :data-today="day.isToday"
                            class="grid h-8 w-8 place-items-center justify-self-center rounded text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-30"
                            :class="[
                                day.isSelected ? 'bg-primary text-white font-medium' : 'hover:bg-surface-hover',
                                !day.isSelected && day.isToday ? 'ring-1 ring-inset ring-primary text-primary font-medium' : '',
                                !day.isSelected && !day.isToday && day.inMonth ? 'text-foreground' : '',
                                !day.isSelected && !day.isToday && !day.inMonth ? 'text-foreground-muted' : '',
                            ]"
                            @click="select(day)"
                        >
                            {{ day.day }}
                        </button>
                    </div>

                    <div class="mt-2 flex items-center justify-between border-t pt-2">
                        <button type="button" class="text-xs font-medium text-primary hover:underline" @click="selectToday">
                            Today
                        </button>
                        <button v-if="clearable && model" type="button" class="text-xs text-foreground-muted hover:text-foreground hover:underline" @click="clear">
                            Clear
                        </button>
                    </div>
                    </template>
                </div>
            </Transition>
        </div>
        <p v-if="error" class="mt-1.5 text-sm text-danger">{{ error }}</p>
        <p v-else-if="helper" class="mt-1.5 text-sm text-foreground-muted">{{ helper }}</p>
    </div>
</template>
