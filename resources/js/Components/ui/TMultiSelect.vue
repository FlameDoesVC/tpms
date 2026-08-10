<script setup>
import { computed, ref } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { useAnchoredPanel } from '@/composables/useAnchoredPanel';

const props = defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    options: { type: Array, default: () => [] },
    // Shown when nothing is picked - an empty selection reads as "no filter
    // applied" everywhere this is used, so this doubles as that state's label.
    placeholder: { type: String, default: 'All' },
    width: { type: String, default: null },
});

const model = defineModel({ type: Array, default: () => [] });

const open = ref(false);
const anchorRef = ref(null);
const panelRef = ref(null);

const close = () => { open.value = false; };

// Teleported to <body> so an ancestor's `overflow-hidden` - a TModal dialog, a
// TCard, a scrolling table - can't clip the list.
const { panelStyle } = useAnchoredPanel({
    open,
    anchorRef,
    panelRef,
    // Wide enough for the option labels, but never narrower than the trigger.
    minWidth: 224, // w-56
    estimatedHeight: 260,
    onClose: close,
});

const isChecked = (value) => model.value.includes(value);

const toggleValue = (value) => {
    model.value = isChecked(value)
        ? model.value.filter((v) => v !== value)
        : [...model.value, value];
};

const selectAll = () => { model.value = props.options.map((o) => o.value); };
const clearAll = () => { model.value = []; };

// 0 picked -> placeholder. 1-2 -> spell them out, that's more useful at a
// glance than a bare count. 3+ -> a count, or the trigger would overflow.
const triggerLabel = computed(() => {
    if (model.value.length === 0) return props.placeholder;
    if (model.value.length <= 2) {
        return props.options
            .filter((o) => model.value.includes(o.value))
            .map((o) => o.label)
            .join(', ');
    }
    return `${model.value.length} selected`;
});

const toggle = () => { open.value = !open.value; };

const onTriggerKeydown = (e) => {
    if (['Enter', ' ', 'ArrowDown'].includes(e.key)) { e.preventDefault(); open.value = true; }
    // Stopped here, or the Escape that closes the list would also close the
    // modal the field sits in.
    else if (e.key === 'Escape' && open.value) { e.stopPropagation(); close(); }
};
</script>

<template>
    <div>
        <label v-if="label" class="mb-1.5 block text-sm font-medium text-foreground">{{ label }}</label>
        <div ref="anchorRef" class="relative" :style="width ? { width } : {}">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-2 rounded-lg border bg-surface px-3 py-2 text-left text-sm shadow-xs transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                :class="error ? 'border-danger' : ''"
                @click="toggle"
                @keydown="onTriggerKeydown"
            >
                <span class="truncate" :class="model.length ? 'text-foreground' : 'text-foreground-muted'">
                    {{ triggerLabel }}
                </span>
                <span class="flex shrink-0 items-center gap-1">
                    <span v-if="model.length" class="grid h-4 min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-semibold text-white">
                        {{ model.length }}
                    </span>
                    <TIcon name="chevronDown" :size="15" class="text-foreground-muted transition-transform" :class="open ? 'rotate-180' : ''" />
                </span>
            </button>

            <Teleport to="body">
            <Transition
                enter-active-class="transition duration-100 ease-out"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div v-if="open" ref="panelRef" class="elevated-lg fixed z-[60] origin-top rounded-lg border bg-surface" :style="panelStyle" @keydown.esc.stop="close">
                    <ul class="max-h-56 overflow-auto py-1" role="listbox" aria-multiselectable="true">
                        <li
                            v-for="option in options"
                            :key="option.value"
                            role="option"
                            :aria-selected="isChecked(option.value)"
                        >
                            <label class="flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm text-foreground transition-colors hover:bg-surface-hover">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 shrink-0 rounded border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                                    :checked="isChecked(option.value)"
                                    @change="toggleValue(option.value)"
                                />
                                <span class="truncate">{{ option.label }}</span>
                            </label>
                        </li>
                        <li v-if="options.length === 0" class="px-3 py-2 text-sm text-foreground-muted">
                            No options
                        </li>
                    </ul>
                    <div v-if="options.length" class="flex items-center justify-between border-t px-3 py-2">
                        <button type="button" class="text-xs font-medium text-primary hover:underline" @click="selectAll">
                            Select all
                        </button>
                        <button v-if="model.length" type="button" class="text-xs text-foreground-muted hover:text-foreground hover:underline" @click="clearAll">
                            Clear
                        </button>
                    </div>
                </div>
            </Transition>
            </Teleport>
        </div>
        <p v-if="error" class="mt-1.5 text-sm text-danger">{{ error }}</p>
    </div>
</template>
