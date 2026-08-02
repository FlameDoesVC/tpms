<script setup>
import { computed } from 'vue';

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    locations: {
        type: Array,
        default: () => [],
    },
});

// Hardcoded fallback zones used when no DB locations are loaded yet.
const FALLBACK_ZONES = {
    'North Shore':    { position_top: 31, position_left: 39 },
    'Marine Theatre': { position_top: 48, position_left: 70 },
    'Main Beach':     { position_top: 56, position_left: 45 },
};

const TYPE_COLOR = {
    hotel:     '#22c55e',
    ferry:     '#f59e0b',
    themepark: '#3b82f6',
    beach:     '#06b6d4',
    general:   '#8b5cf6',
};

const TYPE_LABEL = {
    hotel:     'Hotel',
    ferry:     'Ferry',
    themepark: 'Theme Park',
    beach:     'Beach',
    general:   'General',
};

// Build a name → event list map from the events prop.
const eventsByLocation = computed(() => {
    const map = new Map();
    for (const event of props.events) {
        if (!map.has(event.location)) map.set(event.location, []);
        map.get(event.location).push(event);
    }
    return map;
});

// When DB locations are available use them; fall back to hardcoded zones
// derived from event locations so the map still works before any admin setup.
const markers = computed(() => {
    if (props.locations.length > 0) {
        return props.locations.map((loc) => ({
            id: loc.id,
            name: loc.name,
            description: loc.description,
            type: loc.type,
            color: TYPE_COLOR[loc.type] ?? TYPE_COLOR.general,
            typeLabel: TYPE_LABEL[loc.type] ?? loc.type,
            top: `${loc.position_top}%`,
            left: `${loc.position_left}%`,
            events: eventsByLocation.value.get(loc.name) ?? [],
        }));
    }

    // Fallback: build markers from hardcoded zones + event locations.
    const byZone = new Map();
    for (const event of props.events) {
        if (!FALLBACK_ZONES[event.location]) continue;
        if (!byZone.has(event.location)) byZone.set(event.location, []);
        byZone.get(event.location).push(event);
    }
    return Array.from(byZone.entries()).map(([name, events]) => ({
        id: name,
        name,
        description: null,
        type: 'general',
        color: TYPE_COLOR.general,
        typeLabel: 'Location',
        top: `${FALLBACK_ZONES[name].position_top}%`,
        left: `${FALLBACK_ZONES[name].position_left}%`,
        events,
    }));
});

const unmappedLocations = computed(() => {
    if (props.locations.length === 0) {
        const knownNames = new Set(Object.keys(FALLBACK_ZONES));
        return [...new Set(props.events.map((e) => e.location).filter((l) => !knownNames.has(l)))];
    }
    const knownNames = new Set(props.locations.map((l) => l.name));
    return [...new Set(props.events.map((e) => e.location).filter((l) => !knownNames.has(l)))];
});
</script>

<template>
    <div class="rounded-xl border bg-surface p-4">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-foreground">Island Map</h2>
                <p class="mt-1 text-sm text-foreground-muted">Picnic Island — tap a pin to see what's there.</p>
            </div>

            <!-- Legend -->
            <div v-if="locations.length > 0" class="flex flex-wrap justify-end gap-x-4 gap-y-1">
                <div
                    v-for="(color, type) in TYPE_COLOR"
                    :key="type"
                    class="flex items-center gap-1.5 text-xs text-foreground-muted"
                >
                    <span class="h-2.5 w-2.5 rounded-full" :style="{ background: color }" />
                    {{ TYPE_LABEL[type] }}
                </div>
            </div>
        </div>

        <div class="relative mt-2 aspect-[900/869] w-full overflow-hidden rounded-lg bg-surface-hover">
            <img
                src="/images/velaafinolhu.png"
                alt="Map of Picnic Island"
                class="absolute inset-0 h-full w-full object-cover"
            />

            <button
                v-for="marker in markers"
                :key="marker.id"
                type="button"
                class="group absolute -translate-x-1/2 -translate-y-full"
                :style="{ top: marker.top, left: marker.left }"
            >
                <!-- Pin SVG coloured by type -->
                <svg
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    class="h-8 w-8 drop-shadow-md transition-transform group-hover:scale-125"
                    :style="{ color: marker.color }"
                >
                    <path d="M12 2C7.6 2 4 5.6 4 10c0 6 8 12 8 12s8-6 8-12c0-4.4-3.6-8-8-8zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" />
                </svg>

                <!-- Tooltip -->
                <div
                    class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 w-52 -translate-x-1/2 rounded-lg bg-gray-900/95 p-3 text-left text-xs text-white opacity-0 shadow-xl transition-opacity group-hover:opacity-100"
                >
                    <div class="flex items-center gap-1.5 mb-1">
                        <span class="h-2 w-2 rounded-full shrink-0" :style="{ background: marker.color }" />
                        <span class="text-gray-400 uppercase tracking-wide text-[10px]">{{ marker.typeLabel }}</span>
                    </div>
                    <p class="font-semibold text-white">{{ marker.name }}</p>
                    <p v-if="marker.description" class="mt-0.5 text-gray-300">{{ marker.description }}</p>
                    <template v-if="marker.events.length">
                        <p class="mt-2 text-gray-400 text-[10px] uppercase tracking-wide">Events here</p>
                        <p v-for="event in marker.events" :key="event.id" class="mt-0.5 text-gray-200">
                            {{ event.name }}
                        </p>
                    </template>
                </div>
            </button>

            <div v-if="markers.length === 0" class="absolute inset-0 flex items-center justify-center">
                <p class="rounded-lg bg-black/40 px-4 py-2 text-sm text-white">
                    No locations added yet — admin can add pins from the Map Management page.
                </p>
            </div>
        </div>

        <p v-if="unmappedLocations.length" class="mt-3 text-xs text-foreground-muted">
            Also happening elsewhere: {{ unmappedLocations.join(', ') }}
        </p>
    </div>
</template>
