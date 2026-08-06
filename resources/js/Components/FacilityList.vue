<script setup>
/**
 * Facility or amenity slugs, rendered as labelled icons.
 *
 * `chips` is the compact form for listing cards and card headers; `grid` is the
 * "what this place has" block on a detail page. Both resolve their labels
 * through utils/facilities.js, so a slug the frontend has not been taught yet
 * still renders readably instead of as an empty chip.
 */
import { computed } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { facilityMeta } from '@/utils/facilities';

const props = defineProps({
    items: { type: Array, default: () => [] },
    variant: { type: String, default: 'chips' }, // chips | grid
    limit: { type: Number, default: 0 },
    size: { type: Number, default: 14 },
});

const resolved = computed(() => props.items.map(facilityMeta));
const shown = computed(() => (props.limit > 0 ? resolved.value.slice(0, props.limit) : resolved.value));
const overflow = computed(() => Math.max(0, resolved.value.length - shown.value.length));
</script>

<template>
    <ul
        v-if="resolved.length"
        :class="
            variant === 'grid'
                ? 'grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3'
                : 'flex flex-wrap items-center gap-1.5'
        "
    >
        <li
            v-for="facility in shown"
            :key="facility.slug"
            :class="
                variant === 'grid'
                    ? 'flex items-center gap-2.5 text-sm text-foreground-secondary'
                    : 'inline-flex items-center gap-1.5 rounded-lg border bg-surface-sunken px-2 py-1 text-xs text-foreground-secondary'
            "
        >
            <TIcon
                :name="facility.icon"
                :size="variant === 'grid' ? 18 : size"
                :class="variant === 'grid' ? 'text-primary' : 'text-foreground-muted'"
            />
            <span>{{ facility.label }}</span>
        </li>

        <li
            v-if="overflow"
            class="inline-flex items-center rounded-lg border border-dashed px-2 py-1 text-xs text-foreground-muted"
        >
            +{{ overflow }} more
        </li>
    </ul>
</template>
