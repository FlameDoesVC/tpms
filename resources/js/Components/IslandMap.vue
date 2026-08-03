<script setup>
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

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

// Picnic Island, real world coordinates. Panning/zooming is locked to a
// rectangle around it so the map can't be dragged off into open ocean.
const CENTER = [2.171568, 73.079713];
const BOUNDS = L.latLngBounds([2.165568, 73.072713], [2.177568, 73.086713]);

// Satellite imagery, no separate dark-mode variant needed.
const SATELLITE_TILE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
const SATELLITE_ATTRIBUTION = 'Tiles &copy; Esri &mdash; Esri, Maxar, Earthstar Geographics, and the GIS User Community';

// Hardcoded fallback zones used when no DB locations are loaded yet, spread
// around the real center point so the map still shows something meaningful
// before any admin setup.
const FALLBACK_ZONES = {
    'North Shore':    { lat: 2.173848, lng: 73.078173 },
    'Marine Theatre': { lat: 2.171808, lng: 73.082513 },
    'Main Beach':     { lat: 2.170848, lng: 73.079013 },
};

const TYPE_COLOR = {
    hotel:     '#22c55e',
    ferry:     '#f59e0b',
    themepark: '#3b82f6',
    beach:     '#06b6d4',
    general:   '#eab308',
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
            lat: loc.latitude,
            lng: loc.longitude,
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
        lat: FALLBACK_ZONES[name].lat,
        lng: FALLBACK_ZONES[name].lng,
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

const pinIcon = (color) => L.divIcon({
    className: '',
    html: `
        <svg viewBox="0 0 24 24" fill="${color}" width="32" height="32" style="filter: drop-shadow(0 2px 2px rgb(0 0 0 / 0.4))">
            <path d="M12 2C7.6 2 4 5.6 4 10c0 6 8 12 8 12s8-6 8-12c0-4.4-3.6-8-8-8zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" />
        </svg>
    `,
    iconSize: [32, 32],
    iconAnchor: [16, 32],
    tooltipAnchor: [0, -28],
});

const tooltipHtml = (marker) => `
    <div class="flex items-center gap-1.5 mb-1">
        <span class="h-2 w-2 rounded-full shrink-0" style="background:${marker.color}"></span>
        <span class="text-gray-400 uppercase tracking-wide text-[10px]">${marker.typeLabel}</span>
    </div>
    <p class="font-semibold text-white">${marker.name}</p>
    ${marker.description ? `<p class="mt-0.5 text-gray-300">${marker.description}</p>` : ''}
    ${marker.events.length ? `
        <p class="mt-2 text-gray-400 text-[10px] uppercase tracking-wide">Events here</p>
        ${marker.events.map((e) => `<p class="mt-0.5 text-gray-200">${e.name}</p>`).join('')}
    ` : ''}
`;

const mapContainer = ref(null);
let map = null;
let tileLayer = null;
let markerLayer = null;

const renderMarkers = () => {
    if (!map) return;
    markerLayer?.clearLayers();
    markerLayer = markerLayer ?? L.layerGroup().addTo(map);
    for (const marker of markers.value) {
        L.marker([marker.lat, marker.lng], { icon: pinIcon(marker.color) })
            .bindTooltip(tooltipHtml(marker), {
                direction: 'top',
                offset: [0, -28],
                className: 'tp-map-tooltip',
                opacity: 1,
            })
            .addTo(markerLayer);
    }
};

const applyTileLayer = () => {
    if (!map) return;
    tileLayer?.remove();
    tileLayer = L.tileLayer(SATELLITE_TILE_URL, {
        attribution: SATELLITE_ATTRIBUTION,
        maxZoom: 19,
        // Esri's imagery for this area only has real resolution up to ~17;
        // past that, upscale the last tile instead of showing their
        // "Map data not yet available" placeholder.
        maxNativeZoom: 17,
    }).addTo(map);
};

onMounted(async () => {
    await nextTick();
    map = L.map(mapContainer.value, {
        center: CENTER,
        zoom: 15,
        // Fractional zoom so fitBounds can crop tightly to BOUNDS instead of
        // snapping down to the nearest whole level and showing extra area
        // (which was pulling neighbouring islands into view).
        zoomSnap: 0,
        scrollWheelZoom: false,
        maxBounds: BOUNDS,
        maxBoundsViscosity: 1.0,
    });
    map.fitBounds(BOUNDS);
    map.setMinZoom(map.getZoom());
    map.setMaxZoom(Math.min(map.getZoom() + 5, 19));
    map.setView(CENTER, map.getZoom());

    applyTileLayer();
    renderMarkers();
});

onUnmounted(() => {
    map?.remove();
    map = null;
});

watch(markers, renderMarkers);
</script>

<template>
    <div class="rounded-xl border bg-surface p-4">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-foreground">Island Map</h2>
                <p class="mt-1 text-sm text-foreground-muted">Picnic Island — drag or scroll to explore, tap a pin to see what's there.</p>
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
            <div ref="mapContainer" class="absolute inset-0 h-full w-full" />

            <div v-if="markers.length === 0" class="pointer-events-none absolute inset-0 z-[500] flex items-center justify-center">
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

<style>
/* Leaflet's default tooltip chrome (white box + arrow) clashes with our own
   dark tooltip markup, so strip it down to a bare positioned container. */
.tp-map-tooltip {
    background: rgba(17, 24, 39, 0.95);
    border: none;
    border-radius: 0.5rem;
    padding: 0.75rem;
    width: 13rem;
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.3);
}
.tp-map-tooltip::before {
    display: none;
}
</style>
