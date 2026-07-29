<script setup>
import { computed } from 'vue';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
});

// Fixed pin positions (percent of the map frame), calibrated against
// public/images/little-saint-james.jpg. Keep in sync with the location
// strings staff use when creating events.
const ZONES = {
    'North Shore': { top: '17%', left: '62%' },
    'Marine Theatre': { top: '46%', left: '76%' },
    'Main Beach': { top: '78%', left: '28%' },
};

const markers = computed(() => {
    const byZone = new Map();
    for (const event of props.events) {
        if (!ZONES[event.location]) continue;
        if (!byZone.has(event.location)) byZone.set(event.location, []);
        byZone.get(event.location).push(event);
    }
    return Array.from(byZone.entries()).map(([location, events]) => ({
        location,
        events,
        ...ZONES[location],
    }));
});

const otherLocations = computed(() => {
    const seen = new Set();
    for (const event of props.events) {
        if (!ZONES[event.location]) seen.add(event.location);
    }
    return Array.from(seen);
});
</script>

<template>
    <div class="rounded-xl border bg-surface p-4">
        <h2 class="text-xl font-semibold text-foreground">Island Map</h2>
        <p class="mt-1 text-sm text-foreground-muted">Little Saint James - where today's events are happening.</p>

        <div class="relative mt-4 aspect-[900/869] w-full overflow-hidden rounded-lg">
            <img
                src="/images/little-saint-james.jpg"
                alt="Map of Little Saint James"
                class="absolute inset-0 h-full w-full object-cover"
            />

            <button
                v-for="marker in markers"
                :key="marker.location"
                type="button"
                class="group absolute -translate-x-1/2 -translate-y-full"
                :style="{ top: marker.top, left: marker.left }"
            >
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-8 w-8 text-primary drop-shadow">
                    <path d="M12 2C7.6 2 4 5.6 4 10c0 6 8 12 8 12s8-6 8-12c0-4.4-3.6-8-8-8zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" />
                </svg>
                <div
                    class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 w-48 -translate-x-1/2 rounded-md bg-gray-900/90 p-2 text-left text-xs text-white opacity-0 shadow-lg transition group-hover:opacity-100"
                >
                    <p class="font-semibold">{{ marker.location }}</p>
                    <p v-for="event in marker.events" :key="event.id" class="mt-0.5 text-gray-200">
                        {{ event.name }}
                    </p>
                </div>
            </button>
        </div>

        <p v-if="otherLocations.length" class="mt-3 text-xs text-foreground-muted">
            Also happening elsewhere on the island: {{ otherLocations.join(', ') }}
        </p>
    </div>
</template>
