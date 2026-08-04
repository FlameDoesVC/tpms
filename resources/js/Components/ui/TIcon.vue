<script setup>
import { computed } from 'vue';
// One curated monoline set, drawn on a 24px grid at 1.5 stroke with round
// caps - that shared discipline is what stops the drift into mismatched
// stroke weights that made the old hand-rolled SVGs look untidy.
//
// The data now lives in utils/icons.js because Leaflet map pins are built as
// HTML strings and cannot mount a component; both read the same paths.
import { ICON_PATHS } from '@/utils/icons';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: [String, Number], default: 20 },
    stroke: { type: [String, Number], default: 1.5 },
});

const d = computed(() => ICON_PATHS[props.name] ?? ICON_PATHS.info);
const px = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size));
</script>

<template>
    <svg
        :width="px"
        :height="px"
        viewBox="0 0 24 24"
        fill="none"
        :stroke-width="stroke"
        stroke="currentColor"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        focusable="false"
        class="shrink-0"
    >
        <path :d="d" />
    </svg>
</template>
