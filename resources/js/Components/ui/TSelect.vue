<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';

const props = defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Select…' },
    searchable: { type: Boolean, default: false },
});

const model = defineModel();
const open = ref(false);
const search = ref('');
const highlighted = ref(-1);
const wrapperRef = ref(null);
const searchRef = ref(null);
const listRef = ref(null);

const selectedLabel = computed(() => {
    const opt = props.options.find(o => o.value === model.value);
    return opt ? opt.label : '';
});

const filtered = computed(() => {
    if (!search.value) return props.options;
    const q = search.value.toLowerCase();
    return props.options.filter(o => o.label.toLowerCase().includes(q));
});

const toggle = () => {
    open.value = !open.value;
    if (open.value) {
        highlighted.value = filtered.value.findIndex(o => o.value === model.value);
        nextTick(() => searchRef.value?.focus());
    } else {
        search.value = '';
    }
};

const select = (option) => {
    if (option.disabled) return;
    model.value = option.value;
    open.value = false;
    search.value = '';
};

// Arrow keys land only on selectable options, so a run of disabled ones
// (e.g. sold-out slots) is stepped over rather than trapping the cursor.
const moveHighlight = (step) => {
    const opts = filtered.value;
    if (!opts.length) return;
    let i = highlighted.value;
    for (let n = 0; n < opts.length; n++) {
        i += step;
        if (i < 0 || i >= opts.length) return;
        if (!opts[i].disabled) {
            highlighted.value = i;
            scrollToHighlighted();
            return;
        }
    }
};

const scrollToHighlighted = () => {
    nextTick(() => {
        const el = listRef.value?.children[highlighted.value];
        el?.scrollIntoView({ block: 'nearest' });
    });
};

const onKeydown = (e) => {
    if (!open.value) {
        if (['Enter', ' ', 'ArrowDown'].includes(e.key)) {
            e.preventDefault();
            toggle();
        }
        return;
    }
    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            moveHighlight(1);
            break;
        case 'ArrowUp':
            e.preventDefault();
            moveHighlight(-1);
            break;
        case 'Enter':
            e.preventDefault();
            if (highlighted.value >= 0 && filtered.value[highlighted.value]) {
                select(filtered.value[highlighted.value]);
            }
            break;
        case 'Escape':
            open.value = false;
            search.value = '';
            break;
    }
};

watch(search, () => {
    highlighted.value = filtered.value.findIndex(o => !o.disabled);
});

const onClickOutside = (e) => {
    if (wrapperRef.value && !wrapperRef.value.contains(e.target)) {
        open.value = false;
        search.value = '';
    }
};

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
    <div ref="wrapperRef">
        <label v-if="label" class="mb-1.5 block text-sm font-medium text-foreground">{{ label }}</label>
        <div class="relative">
            <button
                type="button"
                class="flex w-full items-center justify-between rounded-lg border bg-surface px-3 py-2 text-left text-sm shadow-sm transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                :class="error ? 'border-danger' : ''"
                @click="toggle"
                @keydown="onKeydown"
            >
                <span :class="selectedLabel ? 'text-foreground' : 'text-foreground-muted'">
                    {{ selectedLabel || placeholder }}
                </span>
                <svg class="h-4 w-4 text-foreground-muted transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="open" class="elevated-lg absolute z-50 mt-1 w-full origin-top rounded-lg border bg-surface">
                    <div v-if="searchable" class="border-b p-2">
                        <input
                            ref="searchRef"
                            v-model="search"
                            type="text"
                            class="w-full rounded-md border bg-surface px-3 py-1.5 text-sm text-foreground placeholder:text-foreground-muted focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary/20"
                            placeholder="Search…"
                            @keydown="onKeydown"
                        />
                    </div>
                    <ul ref="listRef" class="max-h-60 overflow-auto py-1" role="listbox">
                        <li
                            v-for="(option, i) in filtered"
                            :key="option.value"
                            role="option"
                            :aria-selected="option.value === model"
                            :aria-disabled="!!option.disabled"
                            class="px-3 py-2 text-sm transition-colors"
                            :class="[
                                option.disabled
                                    ? 'cursor-not-allowed text-foreground-muted opacity-60'
                                    : 'cursor-pointer',
                                !option.disabled && i === highlighted ? 'bg-primary-soft text-primary' : '',
                                !option.disabled && i !== highlighted ? 'text-foreground hover:bg-surface-hover' : '',
                                option.value === model ? 'font-medium' : '',
                            ]"
                            @click="select(option)"
                            @mouseenter="option.disabled || (highlighted = i)"
                        >
                            <slot name="option" :option="option">
                                {{ option.label }}
                            </slot>
                        </li>
                        <li v-if="filtered.length === 0" class="px-3 py-2 text-sm text-foreground-muted">
                            No results
                        </li>
                    </ul>
                </div>
            </Transition>
        </div>
        <p v-if="error" class="mt-1.5 text-sm text-danger">{{ error }}</p>
    </div>
</template>
