<script setup>
/**
 * The island map.
 *
 * It used to be imagery with pins on it and nothing else: no way to tell what
 * a colour meant, no way to narrow it down, a fixed frame that ignored where
 * the pins actually were, and a wheel that silently ate the page scroll. This
 * version pairs the map with a panel that lists the same places, and the two
 * stay in step - filter or search in the panel and the map refits to what's
 * left; pick a row and the map flies to it.
 *
 * On colour: four independent hues cannot clear both the CVD floor and the
 * normal-vision floor at once, so colour encodes the LEG of the trip (three
 * hues, validated against both themes) and the pin's glyph encodes the exact
 * type. Identity is therefore never carried by colour alone, which is also
 * what makes the beach/park pair legible when they share a hue.
 */
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import TIcon from '@/Components/ui/TIcon.vue';
import TInput from '@/Components/ui/TInput.vue';
import FilterSection from '@/Components/FilterSection.vue';
import { iconSvg } from '@/utils/icons';

const props = defineProps({
    events: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
});

const router = useRouter();

// Picnic Island, real world coordinates. Panning is fenced to a rectangle
// around it so the map can't be dragged off into open ocean.
const CENTER = [2.171568, 73.079713];
const BOUNDS = L.latLngBounds([2.165568, 73.072713], [2.177568, 73.086713]);

const SATELLITE_TILE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
const SATELLITE_ATTRIBUTION = 'Tiles &copy; Esri &mdash; Esri, Maxar, Earthstar Geographics, and the GIS User Community';

// Hardcoded zones, used while no map locations exist in the database, spread
// around the real centre so the map still says something before admin setup.
const FALLBACK_ZONES = {
    'North Shore': { lat: 2.173848, lng: 73.078173 },
    'Marine Theatre': { lat: 2.171808, lng: 73.082513 },
    'Main Beach': { lat: 2.170848, lng: 73.079013 },
};

/**
 * The three legs of a trip, and the only three hues on this map. Checked with
 * the palette validator across every pair in both themes: worst case ΔE 14.2
 * under simulated CVD and 22.8 with normal vision, comfortably over the 8/15
 * floors.
 */
const LEGS = {
    stay: { label: 'Stay', color: '#0d9488' },
    cross: { label: 'Travel', color: '#c2640c' },
    play: { label: 'Play', color: '#7c3aed' },
    other: { label: 'Other', color: '#6b7280' },
};

// Glyph is the primary identity encoding; leg colour is the grouping cue.
const TYPE_META = {
    hotel: { label: 'Hotels', icon: 'hotel', leg: 'stay' },
    ferry: { label: 'Ferry stops', icon: 'ferry', leg: 'cross' },
    themepark: { label: 'Park', icon: 'sparkle', leg: 'play' },
    beach: { label: 'Beaches', icon: 'anchor', leg: 'play' },
    general: { label: 'Other', icon: 'pin', leg: 'other' },
};
const TYPE_ORDER = ['hotel', 'ferry', 'themepark', 'beach', 'general'];

const metaFor = (type) => TYPE_META[type] ?? TYPE_META.general;
const colorFor = (type) => LEGS[metaFor(type).leg].color;

/* ------------------------------- the data ------------------------------- */

const eventsByLocation = computed(() => {
    const grouped = new Map();
    for (const event of props.events) {
        if (!grouped.has(event.location)) grouped.set(event.location, []);
        grouped.get(event.location).push(event);
    }
    return grouped;
});

const allMarkers = computed(() => {
    if (props.locations.length > 0) {
        return props.locations.map((loc) => ({
            id: `db-${loc.id}`,
            name: loc.name,
            description: loc.description,
            type: loc.type ?? 'general',
            lat: Number(loc.latitude),
            lng: Number(loc.longitude),
            events: eventsByLocation.value.get(loc.name) ?? [],
        }));
    }

    // Fallback: the zones above, but only those an event actually happens in.
    const named = new Map();
    for (const event of props.events) {
        if (!FALLBACK_ZONES[event.location]) continue;
        if (!named.has(event.location)) named.set(event.location, []);
        named.get(event.location).push(event);
    }
    return Array.from(named.entries()).map(([name, events]) => ({
        id: `zone-${name}`,
        name,
        description: null,
        // Guessed from what happens there, so a fallback pin still gets the
        // right glyph rather than all three coming out as generic dots.
        type: /beach|shore|lagoon/i.test(name) ? 'beach' : 'themepark',
        lat: FALLBACK_ZONES[name].lat,
        lng: FALLBACK_ZONES[name].lng,
        events,
    }));
});

const hiddenTypes = ref([]);
const query = ref('');
const selectedId = ref(null);

const presentTypes = computed(() => {
    const counts = new Map();
    for (const marker of allMarkers.value) {
        counts.set(marker.type, (counts.get(marker.type) ?? 0) + 1);
    }
    return TYPE_ORDER.filter((type) => counts.has(type)).map((type) => ({
        type,
        count: counts.get(type),
        ...metaFor(type),
        color: colorFor(type),
    }));
});

// Only the legs actually on the map get a legend entry - a key to colours that
// aren't present is just clutter.
const presentLegs = computed(() => {
    const seen = new Set(allMarkers.value.map((m) => metaFor(m.type).leg));
    return Object.entries(LEGS)
        .filter(([key]) => seen.has(key))
        .map(([key, leg]) => ({ key, ...leg }));
});

const visibleMarkers = computed(() => {
    const needle = query.value.trim().toLowerCase();
    return allMarkers.value.filter((marker) => {
        if (hiddenTypes.value.includes(marker.type)) return false;
        if (!needle) return true;
        const haystack = [marker.name, marker.description, ...marker.events.map((e) => e.name)]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        return haystack.includes(needle);
    });
});

const summary = computed(() => {
    const total = allMarkers.value.length;
    if (!total) return 'Nothing pinned yet';
    const shown = visibleMarkers.value.length;
    const places = `${total} place${total === 1 ? '' : 's'}`;
    return shown === total ? `${places} on the island` : `${shown} of ${places}`;
});

const unmappedLocations = computed(() => {
    const known = new Set(
        props.locations.length > 0
            ? props.locations.map((l) => l.name)
            : Object.keys(FALLBACK_ZONES),
    );
    return [...new Set(props.events.map((e) => e.location).filter((name) => name && !known.has(name)))];
});

const toggleType = (type) => {
    hiddenTypes.value = hiddenTypes.value.includes(type)
        ? hiddenTypes.value.filter((t) => t !== type)
        : [...hiddenTypes.value, type];
};

const clearFilters = () => {
    hiddenTypes.value = [];
    query.value = '';
};

const filtersActive = computed(() => hiddenTypes.value.length > 0 || query.value.trim() !== '');

// Echoes the current state beside the section heading, the way the hotel and
// park filter rails do ("2 selected" / "Any").
const shownHint = computed(() => {
    const shown = presentTypes.value.length - hiddenTypes.value.length;
    return shown === presentTypes.value.length ? 'All' : `${shown} of ${presentTypes.value.length}`;
});

/* -------------------------------- the map -------------------------------- */

const mapContainer = ref(null);
let map = null;
let tileLayer = null;
let markerLayer = null;
let islandZoom = 15;
const leafletMarkers = new Map();

/** Where a pin should take you. */
const targetFor = (marker) => {
    if (marker.events.length === 1) {
        return { name: 'themepark.home', query: { event: marker.events[0].id } };
    }
    if (marker.events.length > 1) return { name: 'themepark.home' };
    if (marker.type === 'hotel') return { name: 'hotels.index' };
    if (marker.type === 'ferry') return { name: 'ferry.book' };
    if (marker.type === 'beach' || marker.type === 'themepark') return { name: 'themepark.home' };
    return null;
};

const pinIcon = (marker, selected = false) => {
    const color = colorFor(marker.type);
    // The white casing is what keeps a pin readable over satellite imagery,
    // where the backdrop can be any colour at all.
    return L.divIcon({
        className: '',
        html: `
            <div class="tp-pin${selected ? ' tp-pin-on' : ''}">
                <svg viewBox="0 0 34 44" width="34" height="44" aria-hidden="true">
                    <path d="M17 42s14-15.5 14-25A14 14 0 1 0 3 17c0 9.5 14 25 14 25z"
                          fill="${color}" stroke="#ffffff" stroke-width="2.5" />
                </svg>
                <span class="tp-pin-glyph">${iconSvg(metaFor(marker.type).icon, { size: 15, color: '#ffffff', stroke: 1.9 })}</span>
            </div>
        `,
        iconSize: [34, 44],
        iconAnchor: [17, 42],
        tooltipAnchor: [0, -36],
    });
};

/** Popup built as a node, not a string, so its action can join the SPA. */
const popupNode = (marker) => {
    const meta = metaFor(marker.type);
    const target = targetFor(marker);
    const el = document.createElement('div');
    el.className = 'tp-pop';

    el.innerHTML = `
        <p class="tp-pop-type">
            <span class="tp-pop-dot" style="background:${colorFor(marker.type)}"></span>
            ${meta.label}
        </p>
        <p class="tp-pop-name"></p>
        ${marker.description ? '<p class="tp-pop-desc"></p>' : ''}
        ${marker.events.length ? `<p class="tp-pop-label">${marker.events.length === 1 ? 'Event here' : `${marker.events.length} events here`}</p><ul class="tp-pop-list"></ul>` : ''}
        ${target ? '<button type="button" class="tp-pop-cta"></button>' : ''}
    `;

    // Names and descriptions are operator-entered, so they go in as text
    // rather than markup - the old tooltip interpolated them straight into an
    // HTML string.
    el.querySelector('.tp-pop-name').textContent = marker.name;
    if (marker.description) el.querySelector('.tp-pop-desc').textContent = marker.description;

    const list = el.querySelector('.tp-pop-list');
    if (list) {
        for (const event of marker.events.slice(0, 4)) {
            const li = document.createElement('li');
            li.textContent = event.name;
            list.append(li);
        }
    }

    const cta = el.querySelector('.tp-pop-cta');
    if (cta && target) {
        cta.textContent = marker.type === 'hotel' ? 'See rooms' : 'See what’s on';
        cta.addEventListener('click', () => router.push(target));
    }

    return el;
};

const renderMarkers = () => {
    if (!map) return;
    markerLayer = markerLayer ?? L.layerGroup().addTo(map);
    markerLayer.clearLayers();
    leafletMarkers.clear();

    for (const marker of visibleMarkers.value) {
        const layer = L.marker([marker.lat, marker.lng], {
            icon: pinIcon(marker, marker.id === selectedId.value),
            title: marker.name,
            riseOnHover: true,
        })
            .bindTooltip(marker.name, { direction: 'top', offset: [0, -36], className: 'tp-tip', opacity: 1 })
            .bindPopup(popupNode(marker), { className: 'tp-popup', maxWidth: 260, closeButton: true, autoPanPadding: [30, 30] })
            .on('popupopen', () => { selectedId.value = marker.id; })
            .addTo(markerLayer);

        leafletMarkers.set(marker.id, layer);
    }
};

/** Frames whatever is currently on the map, instead of a fixed rectangle. */
const fitToMarkers = ({ animate = true } = {}) => {
    if (!map) return;

    if (visibleMarkers.value.length === 0) {
        map.flyToBounds(BOUNDS, { animate, duration: 0.6 });
        return;
    }

    // At lg the panel floats over the map's left edge (left-6 + w-80), so the
    // fit has to treat that strip as spoken for or it frames pins underneath
    // it. Read at call time - the answer changes with the viewport.
    const panelOverlays = typeof window !== 'undefined' && window.matchMedia('(min-width: 1024px)').matches;

    const bounds = L.latLngBounds(visibleMarkers.value.map((m) => [m.lat, m.lng]));
    map.flyToBounds(bounds, {
        // The zoom cap stops a lone marker snapping to maximum magnification.
        paddingTopLeft: [panelOverlays ? 376 : 40, 30],
        paddingBottomRight: [40, 70],
        maxZoom: islandZoom + 3,
        animate,
        duration: 0.6,
    });
};

const select = (marker) => {
    selectedId.value = marker.id;
    const layer = leafletMarkers.get(marker.id);
    if (!layer || !map) return;
    map.flyTo([marker.lat, marker.lng], Math.max(map.getZoom(), islandZoom + 2), { duration: 0.6 });
    layer.openPopup();
};

// Only the two pins that change state are redrawn, so selecting a row doesn't
// rebuild every marker on the map.
watch(selectedId, (now, before) => {
    for (const id of [before, now]) {
        if (!id) continue;
        const layer = leafletMarkers.get(id);
        const marker = visibleMarkers.value.find((m) => m.id === id);
        if (layer && marker) layer.setIcon(pinIcon(marker, id === now));
    }
});

/**
 * Cooperative gestures.
 *
 * A 75vh map in the middle of a scrolling page must never capture the gesture
 * used to get past it, but it still has to be zoomable. So the two are split by
 * intent rather than by an "activate the map first" click:
 *
 *   wheel alone          -> the page scrolls, untouched
 *   ctrl/cmd + wheel     -> the map zooms about the cursor
 *   one finger           -> the page scrolls (map dragging is off on touch)
 *   two fingers          -> pinch to zoom, and pan with it
 *
 * A trackpad pinch on macOS arrives as a wheel event with ctrlKey set, so that
 * gesture zooms here too without any extra handling.
 */
const isCoarsePointer = () =>
    typeof window !== 'undefined' && window.matchMedia('(pointer: coarse)').matches;

const hint = ref('');
let hintTimer = null;

// Shown only when a gesture is attempted that this map deliberately ignores -
// a teaching moment at the point of confusion, rather than a permanent label.
const flashHint = (message) => {
    hint.value = message;
    clearTimeout(hintTimer);
    hintTimer = setTimeout(() => { hint.value = ''; }, 1900);
};

const onWheel = (event) => {
    if (!map) return;

    if (!event.ctrlKey && !event.metaKey) {
        flashHint(isCoarsePointer() ? 'Pinch to zoom' : 'Hold Ctrl and scroll to zoom');
        return; // no preventDefault: the page keeps the scroll
    }

    // Stops the browser's own ctrl+wheel page zoom as well.
    event.preventDefault();
    const step = event.deltaY < 0 ? 0.8 : -0.8;
    map.setZoomAround(map.mouseEventToContainerPoint(event), map.getZoom() + step, { animate: false });
};

// One finger is a scroll, not a pan - but say so, or the map looks broken.
const onTouchMove = (event) => {
    if (event.touches.length === 1) flashHint('Use two fingers to move the map');
};

const zoomBy = (key) => {
    if (key === 'in') map?.zoomIn();
    else if (key === 'out') map?.zoomOut();
    else fitToMarkers();
};

onMounted(async () => {
    await nextTick();

    map = L.map(mapContainer.value, {
        center: CENTER,
        zoom: 15,
        // Fractional zoom, so a fit crops tightly instead of snapping down a
        // whole level and pulling neighbouring islands into frame. Also what
        // lets ctrl+wheel and pinch zoom smoothly rather than in whole steps.
        zoomSnap: 0,
        // Handled by hand in onWheel, gated on ctrl/cmd.
        scrollWheelZoom: false,
        // On touch, one finger belongs to the page. Two-finger pinch still
        // zooms AND pans via touchZoom, so the map stays fully navigable.
        dragging: !isCoarsePointer(),
        touchZoom: true,
        zoomControl: false,
        attributionControl: true,
        maxBounds: BOUNDS.pad(0.25),
        maxBoundsViscosity: 0.9,
    });

    // The island's own frame sets the floor: however tightly the pins are
    // grouped, you can always zoom back out to the whole island and no
    // further.
    map.fitBounds(BOUNDS);
    islandZoom = map.getZoom();
    map.setMinZoom(islandZoom);
    map.setMaxZoom(Math.min(islandZoom + 5, 19));

    tileLayer = L.tileLayer(SATELLITE_TILE_URL, {
        attribution: SATELLITE_ATTRIBUTION,
        maxZoom: 19,
        // Esri's imagery here only has real detail to ~17; past that, upscale
        // the last good tile rather than show their "not available" plate.
        maxNativeZoom: 17,
    }).addTo(map);

    // passive:false so onWheel can preventDefault when ctrl/cmd is held.
    mapContainer.value.addEventListener('wheel', onWheel, { passive: false });
    mapContainer.value.addEventListener('touchmove', onTouchMove, { passive: true });

    renderMarkers();
    fitToMarkers({ animate: false });
});

onUnmounted(() => {
    clearTimeout(hintTimer);
    mapContainer.value?.removeEventListener('wheel', onWheel);
    mapContainer.value?.removeEventListener('touchmove', onTouchMove);
    map?.remove();
    map = null;
    tileLayer = null;
    markerLayer = null;
    leafletMarkers.clear();
});

// Filtering is the one case where the frame should follow the data: hide a
// type and the map closes in on what's left.
watch(visibleMarkers, () => {
    renderMarkers();
    fitToMarkers();
});
</script>

<template>
    <section>
        <!-- Header on the page, not inside a card - the same shape as the rail
             headers above, so the map reads as one more section of the page
             rather than a widget dropped onto it. The shell comes from the
             parent section, so this must not add its own. -->
        <header class="mb-5">
            <h2 class="text-2xl font-semibold tracking-tight text-foreground">Island map</h2>
            <p class="mt-1 text-sm text-foreground-muted">
                {{ summary }} — pick one to see what's there.
            </p>
        </header>

        <div class="relative">
            <!-- ------------------------------ map ------------------------------ -->
            <!-- Held to the same measure as every rail and card on the page.
                 The tools float on the water rather than sitting in a strip of
                 chrome beside it. -->
            <div class="elevated relative h-[75vh] min-h-[30rem] overflow-hidden rounded-xl border bg-surface-hover">
                <div ref="mapContainer" class="absolute inset-0 h-full w-full" />

                <!-- Custom controls, at Leaflet's own control layer so they sit
                     above popups exactly as its native ones would. -->
                <div class="absolute right-3 top-3 z-[800] flex flex-col gap-1.5">
                    <button
                        v-for="control in [
                            { key: 'in', icon: 'plus', label: 'Zoom in' },
                            { key: 'out', icon: 'minus', label: 'Zoom out' },
                            { key: 'fit', icon: 'map', label: 'Fit the whole island' },
                        ]"
                        :key="control.key"
                        type="button"
                        class="elevated grid h-9 w-9 place-items-center rounded-lg border bg-surface/95 text-foreground-secondary backdrop-blur transition-colors hover:bg-surface hover:text-foreground"
                        :aria-label="control.label"
                        :title="control.label"
                        @click="zoomBy(control.key)"
                    >
                        <TIcon :name="control.icon" :size="17" />
                    </button>
                </div>

                <!-- Bottom-centre: clear of the panel (top-left), the zoom
                     controls (top-right) and Esri's attribution, which Leaflet
                     paints above everything. -->
                <transition
                    enter-active-class="transition-opacity duration-150"
                    leave-active-class="transition-opacity duration-500"
                    enter-from-class="opacity-0"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="hint"
                        class="pointer-events-none absolute bottom-4 left-1/2 z-[800] -translate-x-1/2 whitespace-nowrap rounded-lg bg-[var(--overlay)] px-3 py-1.5 text-xs font-medium text-white backdrop-blur-sm"
                    >
                        {{ hint }}
                    </p>
                </transition>

                <div
                    v-if="allMarkers.length === 0"
                    class="pointer-events-none absolute inset-0 z-[750] grid place-items-center p-6"
                >
                    <p class="elevated max-w-xs rounded-lg border bg-surface/95 px-4 py-3 text-center text-sm text-foreground-secondary backdrop-blur">
                        Nothing is pinned on the island yet. An admin can add places from
                        Map Management.
                    </p>
                </div>
            </div>

            <!-- ---------------------------- panel ---------------------------- -->
            <!-- A card floating on the water at lg, a plain block under the map
                 below that. max-height rather than height is the point: with
                 three places it hugs them; with forty it caps at the band and
                 the list scrolls inside. A sidebar pinned to the map's height
                 was mostly empty surface. -->
            <div
                class="flex min-h-0 flex-col border-b bg-surface lg:elevated-lg lg:absolute lg:left-6 lg:top-6 lg:z-[800] lg:max-h-[calc(100%-3rem)] lg:w-80 lg:overflow-hidden lg:rounded-xl lg:border lg:bg-surface/95 lg:backdrop-blur"
            >
                <!-- Same shape as the hotel and park filter rails: titled
                     sections in the uppercase micro-type, divided by rules,
                     with the current value echoed beside each heading. The
                     search field is the system's TInput with an icon in its
                     prefix slot, so it inherits the app's focus ring, tokens
                     and label wiring instead of hand-rolling all three. -->
                <div class="divide-y">
                    <FilterSection title="Search">
                        <TInput
                            v-model="query"
                            type="search"
                            placeholder="Place or event name"
                            aria-label="Search places on the island"
                        >
                            <template #prefix><TIcon name="search" :size="16" /></template>
                        </TInput>
                    </FilterSection>

                    <!-- The legend made operable: same swatch, but it filters. -->
                    <FilterSection v-if="presentTypes.length > 0" title="Show" :hint="shownHint">
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="entry in presentTypes"
                                :key="entry.type"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                                :class="hiddenTypes.includes(entry.type)
                                    ? 'border-[rgb(var(--color-border))] text-foreground-muted opacity-60 hover:opacity-100'
                                    : 'border-transparent bg-surface-sunken text-foreground'"
                                :aria-pressed="!hiddenTypes.includes(entry.type)"
                                @click="toggleType(entry.type)"
                            >
                                <span :style="{ color: entry.color }">
                                    <TIcon :name="entry.icon" :size="13" />
                                </span>
                                {{ entry.label }}
                                <span class="tabular-nums text-foreground-muted">{{ entry.count }}</span>
                            </button>
                        </div>
                    </FilterSection>
                </div>

                <div class="flex items-baseline justify-between gap-2 border-t px-4 pb-2 pt-3">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                        Places
                    </h3>
                    <button
                        v-if="filtersActive"
                        type="button"
                        class="text-xs font-medium text-primary hover:underline"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <!-- Scrolls inside the panel rather than growing the card, so
                     the map keeps its height however many places there are. -->
                <ul class="max-h-80 min-h-0 flex-1 overflow-y-auto px-2 pb-2 lg:max-h-none">
                    <li v-for="marker in visibleMarkers" :key="marker.id">
                        <button
                            type="button"
                            class="flex w-full items-start gap-2.5 rounded-lg p-2 text-left transition-colors"
                            :class="selectedId === marker.id
                                ? 'bg-primary-soft'
                                : 'hover:bg-surface-hover'"
                            @click="select(marker)"
                        >
                            <span class="mt-0.5 shrink-0" :style="{ color: colorFor(marker.type) }">
                                <TIcon :name="metaFor(marker.type).icon" :size="16" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-foreground">
                                    {{ marker.name }}
                                </span>
                                <span class="mt-0.5 block text-xs text-foreground-muted">
                                    {{ metaFor(marker.type).label }}
                                    <template v-if="marker.events.length">
                                        · {{ marker.events.length }}
                                        event{{ marker.events.length === 1 ? '' : 's' }}
                                    </template>
                                </span>
                            </span>
                        </button>
                    </li>
                    <li v-if="visibleMarkers.length === 0 && allMarkers.length > 0" class="p-3">
                        <p class="text-sm text-foreground-muted">
                            Nothing matches. <button type="button" class="font-medium text-primary hover:underline" @click="clearFilters">Clear filters</button>
                        </p>
                    </li>
                </ul>

                <!-- What the colours mean, folded in with the chips that use
                     them instead of floating in its own box on the water. -->
                <div
                    v-if="presentLegs.length > 1"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t px-4 py-2.5"
                >
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-foreground-muted">
                        Colour
                    </span>
                    <span
                        v-for="leg in presentLegs"
                        :key="leg.key"
                        class="flex items-center gap-1.5 text-xs text-foreground-secondary"
                    >
                        <span class="h-2.5 w-2.5 rounded-full" :style="{ background: leg.color }" />
                        {{ leg.label }}
                    </span>
                </div>

                <p v-if="unmappedLocations.length" class="border-t p-3 text-xs text-foreground-muted">
                    Also happening elsewhere: {{ unmappedLocations.join(', ') }}
                </p>
            </div>
        </div>
    </section>
</template>

<style>
/* Leaflet renders markers, tooltips and popups outside the component tree, so
   these have to be global. They read the app's own tokens, which is what lets
   one map serve both themes. */
.tp-pin {
    position: relative;
    width: 34px;
    height: 44px;
    filter: drop-shadow(0 3px 3px rgb(0 0 0 / 0.35));
    transition: transform 160ms ease;
}
.tp-pin-glyph {
    position: absolute;
    top: 4px;
    left: 0;
    right: 0;
    display: grid;
    place-items: center;
    height: 28px;
    pointer-events: none;
}
.tp-pin:hover {
    transform: translateY(-2px) scale(1.06);
}
/* The selected pin stands up out of the set. */
.tp-pin-on {
    transform: translateY(-3px) scale(1.12);
    filter: drop-shadow(0 5px 6px rgb(0 0 0 / 0.45));
}

.tp-tip {
    background-color: rgb(var(--color-surface));
    color: rgb(var(--color-text));
    border: 1px solid rgb(var(--color-border));
    border-radius: 0.25rem;
    box-shadow: var(--shadow-md);
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
}
.tp-tip::before {
    display: none;
}

.leaflet-container .tp-popup .leaflet-popup-content-wrapper {
    background-color: rgb(var(--color-surface));
    color: rgb(var(--color-text));
    border: 1px solid rgb(var(--color-border));
    border-radius: 0.375rem;
    box-shadow: var(--shadow-lg);
}
.leaflet-container .tp-popup .leaflet-popup-tip {
    background-color: rgb(var(--color-surface));
    border: 1px solid rgb(var(--color-border));
}
.leaflet-container .tp-popup .leaflet-popup-content {
    margin: 0.75rem;
    font-size: 0.8125rem;
    line-height: 1.45;
}
.tp-pop-type {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.625rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgb(var(--color-text-muted));
}
.tp-pop-dot {
    height: 0.5rem;
    width: 0.5rem;
    border-radius: 9999px;
}
.tp-pop-name {
    margin-top: 0.25rem;
    font-size: 0.9375rem;
    font-weight: 600;
    color: rgb(var(--color-text));
}
.tp-pop-desc {
    margin-top: 0.25rem;
    color: rgb(var(--color-text-secondary));
}
.tp-pop-label {
    margin-top: 0.625rem;
    font-size: 0.625rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgb(var(--color-text-muted));
}
.tp-pop-list {
    margin-top: 0.125rem;
    color: rgb(var(--color-text-secondary));
}
.tp-pop-cta {
    margin-top: 0.75rem;
    width: 100%;
    border-radius: 0.25rem;
    background-color: rgb(var(--color-primary));
    padding: 0.375rem 0.75rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: rgb(255 255 255);
    transition: background-color 150ms ease;
}
[data-theme='dark'] .tp-pop-cta {
    color: rgb(12 12 14);
}
.tp-pop-cta:hover {
    background-color: rgb(var(--color-primary-hover));
}

/* Esri's attribution is a licence condition, so it stays - just quietened
   down and themed rather than a white strip over the water. */
.leaflet-container .leaflet-control-attribution {
    background-color: rgb(var(--color-surface) / 0.8);
    color: rgb(var(--color-text-muted));
    font-size: 10px;
}
.leaflet-container .leaflet-control-attribution a {
    color: rgb(var(--color-text-secondary));
}
</style>
