<script setup>
import { ref } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';

defineProps({
    // What the current filters have narrowed things down to, already worded by
    // the caller ("4 of 9 hotels") - the rail has no idea what it's filtering.
    resultLabel: { type: String, default: null },
    // Number of filters the visitor has actually changed. Drives the badge and
    // whether "Clear all" is offered at all.
    activeCount: { type: Number, default: 0 },
});

defineEmits(['clear']);

// Below xl the rail can't sit beside the results, so it collapses to a single
// button. Closed by default there: filters are a refinement, and opening the
// page on a wall of controls buries the thing you came to look at.
const open = ref(false);
</script>

<template>
    <div>
        <button
            type="button"
            class="elevated flex w-full items-center gap-2 rounded-xl border bg-surface px-4 py-3 text-sm font-medium text-foreground transition-colors hover:bg-surface-hover xl:hidden"
            :aria-expanded="open"
            @click="open = !open"
        >
            <TIcon name="settings" :size="16" class="text-foreground-muted" />
            Filters
            <span
                v-if="activeCount"
                class="grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1.5 text-[11px] font-semibold text-white"
            >
                {{ activeCount }}
            </span>
            <span v-if="resultLabel" class="ml-auto text-xs font-normal text-foreground-muted">{{ resultLabel }}</span>
            <TIcon
                name="chevronDown"
                :size="16"
                class="text-foreground-muted transition-transform"
                :class="open ? 'rotate-180' : ''"
            />
        </button>

        <aside
            aria-label="Filters"
            class="elevated mt-3 overflow-hidden rounded-xl border bg-surface xl:mt-0 xl:block"
            :class="open ? 'block' : 'hidden'"
        >
            <div class="flex items-baseline justify-between gap-2 border-b px-4 py-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-foreground-muted">Refine</p>
                    <p v-if="resultLabel" class="mt-0.5 truncate text-sm font-medium text-foreground">
                        {{ resultLabel }}
                    </p>
                </div>
                <button
                    v-if="activeCount"
                    type="button"
                    class="shrink-0 text-xs font-medium text-primary hover:underline"
                    @click="$emit('clear')"
                >
                    Clear all
                </button>
            </div>

            <div class="divide-y divide-[rgb(var(--color-border))]">
                <slot />
            </div>
        </aside>
    </div>
</template>
