<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSwitch from '@/Components/ui/TSwitch.vue';
import FilterRail from '@/Components/FilterRail.vue';
import FilterSection from '@/Components/FilterSection.vue';
import PromotionsStrip from '@/Components/PromotionsStrip.vue';
import { formatDate, formatDateTime, formatMoney, formatTime, todayIso } from '@/utils/format';
import TIcon from '@/Components/ui/TIcon.vue';
import { useThemeParkStore } from '@/stores/themepark';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const themeParkStore = useThemeParkStore();
const cart = useCartStore();
const auth = useAuthStore();

const TYPES = [
    { key: 'ride', label: 'Rides' },
    { key: 'show', label: 'Shows' },
    { key: 'beach_event', label: 'Beach events' },
];

// Slot times sorted into the three parts of a day people actually plan around.
const BANDS = [
    { key: 'morning', label: 'Morning', hint: 'before noon' },
    { key: 'afternoon', label: 'Afternoon', hint: 'noon – 5pm' },
    { key: 'evening', label: 'Evening', hint: 'after 5pm' },
];

const SORTS = [
    { key: 'time', label: 'Earliest time' },
    { key: 'price', label: 'Cheapest' },
    { key: 'name', label: 'A – Z' },
];

const today = todayIso();

// — Primary search: what the visitor is shopping for.
const date = ref(today);
// Ticket counts are chosen per event, right where the time slot is - a
// visitor booking two attractions rarely wants the same headcount for both,
// and a single page-level count made every second add a two-step edit.
const ticketCounts = reactive({});
const search = ref('');

// — Refinements.
const selectedTypes = ref([]);
const selectedBands = ref([]);
const maxPrice = ref(null);
const hideSoldOut = ref(false);
const sort = ref('time');

// Arriving from a specific event's card (e.g. Welcome.vue) pins the page to
// that one event. Kept separate from the filters so it can be lifted with one
// click without disturbing anything else the visitor has set.
const focusedEventId = ref(null);
const focusedEvent = computed(() =>
    themeParkStore.events.find((e) => e.id === focusedEventId.value) ?? null
);

const slotsByEvent = reactive({});
const loadingSlots = reactive({});
const eventMessages = reactive({});

// Stepped in UTC throughout, so the returned ISO date is the one that was
// asked for rather than whatever the local offset shifts it to.
const addDays = (dateStr, days) => {
    const d = new Date(`${dateStr}T00:00:00Z`);
    d.setUTCDate(d.getUTCDate() + days);
    return d.toISOString().slice(0, 10);
};

const bandOf = (slotTime) => {
    const hour = Number(String(slotTime ?? '').split(':')[0]);
    if (Number.isNaN(hour)) return 'afternoon';
    if (hour < 12) return 'morning';
    if (hour < 17) return 'afternoon';
    return 'evening';
};

// The band filter narrows the times shown inside a card as well as which
// cards survive - a visitor who asked for evenings shouldn't have to scan
// past a morning slot list to find out the card matched on one 8pm show.
const slotsFor = (event) => {
    const all = slotsByEvent[event.id] ?? [];
    return selectedBands.value.length
        ? all.filter((s) => selectedBands.value.includes(bandOf(s.slot_time)))
        : all;
};
const openSlotsFor = (event) => slotsFor(event).filter((s) => s.available_capacity > 0);

// Prices come off the API as decimal strings, so every comparison coerces.
const priceCeiling = computed(() => {
    const prices = themeParkStore.events.map((e) => Number(e.price_per_ticket) || 0);
    return prices.length ? Math.ceil(Math.max(...prices) / 5) * 5 : 0;
});
const priceCap = computed(() => (maxPrice.value == null ? priceCeiling.value : maxPrice.value));
const priceIsCapped = computed(() => priceCeiling.value > 0 && priceCap.value < priceCeiling.value);

const typeCounts = computed(() => {
    const counts = {};
    for (const event of themeParkStore.events) {
        counts[event.type] = (counts[event.type] ?? 0) + 1;
    }
    return counts;
});

const visibleEvents = computed(() => {
    let list = themeParkStore.events;

    if (focusedEventId.value) {
        list = list.filter((e) => e.id === focusedEventId.value);
    }

    const query = search.value.trim().toLowerCase();
    if (query) {
        list = list.filter((e) =>
            `${e.name ?? ''} ${e.description ?? ''} ${e.location ?? ''}`.toLowerCase().includes(query)
        );
    }

    if (selectedTypes.value.length) {
        list = list.filter((e) => selectedTypes.value.includes(e.type));
    }

    if (priceIsCapped.value) {
        list = list.filter((e) => (Number(e.price_per_ticket) || 0) <= priceCap.value);
    }

    if (selectedBands.value.length) {
        list = list.filter((e) => slotsFor(e).length > 0);
    }

    if (hideSoldOut.value) {
        list = list.filter((e) => openSlotsFor(e).length > 0);
    }

    const earliest = (event) => openSlotsFor(event)[0]?.slot_time ?? '99:99';
    const sorted = [...list];
    if (sort.value === 'price') {
        sorted.sort((a, b) => (Number(a.price_per_ticket) || 0) - (Number(b.price_per_ticket) || 0));
    } else if (sort.value === 'name') {
        sorted.sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''));
    } else {
        sorted.sort((a, b) => earliest(a).localeCompare(earliest(b)));
    }
    return sorted;
});

const activeFilterCount = computed(() =>
    (search.value.trim() ? 1 : 0) +
    (selectedTypes.value.length ? 1 : 0) +
    (selectedBands.value.length ? 1 : 0) +
    (priceIsCapped.value ? 1 : 0) +
    (hideSoldOut.value ? 1 : 0) +
    (sort.value !== 'time' ? 1 : 0)
);

const clearFilters = () => {
    search.value = '';
    selectedTypes.value = [];
    selectedBands.value = [];
    maxPrice.value = priceCeiling.value;
    hideSoldOut.value = false;
    sort.value = 'time';
};

const toggleIn = (listRef, value) => {
    listRef.value = listRef.value.includes(value)
        ? listRef.value.filter((v) => v !== value)
        : [...listRef.value, value];
};

const resultLabel = computed(() => {
    const total = themeParkStore.events.length;
    const shown = visibleEvents.value.length;
    if (!total) return null;
    return shown === total ? `${total} event${total === 1 ? '' : 's'}` : `${shown} of ${total} events`;
});

const upcomingBookings = computed(() =>
    themeParkStore.myBookings
        .filter((b) => b.status === 'confirmed' && (b.slot?.slot_date ?? '').slice(0, 10) >= today)
        .slice(0, 3)
);

const selectedSlotIds = reactive({});
const expandedEvents = reactive({});

const toggleExpanded = (eventId) => { expandedEvents[eventId] = !expandedEvents[eventId]; };

const selectedSlotFor = (event) => (slotsByEvent[event.id] ?? []).find((s) => s.id === selectedSlotIds[event.id]) ?? null;

// Summarizes the booking block while it's collapsed, so collapsing doesn't
// hide whether there's anything worth expanding for.
const slotsTeaser = (event) => {
    if (loadingSlots[event.id]) return 'Loading times…';
    const slots = slotsFor(event);
    if (slots.length === 0) return 'No time slots for this date';
    const selected = selectedSlotFor(event);
    if (selected) return `${formatTime(selected.slot_time)} selected`;
    const available = openSlotsFor(event);
    if (available.length === 0) return 'Fully booked for this date';
    return `${available.length} time${available.length === 1 ? '' : 's'} available, from ${formatTime(available[0].slot_time)}`;
};


// Not clamped here - clamping on every keystroke fights the user mid-edit
// (e.g. snapping back to a smaller number as soon as the field is cleared to
// type a new one). Out-of-range values just aren't bookable, per canBook below.
const ticketCountFor = (event) => {
    const chosen = ticketCounts[event.id] ?? 1;
    const slot = selectedSlotFor(event);
    // Never offer more than the chosen slot can seat.
    return slot ? Math.min(chosen, slot.available_capacity) : chosen;
};

const setTicketCount = (event, value) => {
    ticketCounts[event.id] = value;
};

const selectSlot = (event, slot) => {
    if (slot.available_capacity < 1) return;
    selectedSlotIds[event.id] = slot.id;
};

const loadSlotsFor = async (eventId) => {
    if (!date.value) return;

    loadingSlots[eventId] = true;
    try {
        const slots = await themeParkStore.fetchSlotsForEvent(eventId, date.value);
        // Filtered here rather than in the store: staff pages share this
        // endpoint and need cancelled rows. A cancelled slot keeps its
        // capacity, so without this it stays selectable (the server now
        // refuses the booking either way). A missing status means the row
        // predates the column - treat it as bookable.
        slotsByEvent[eventId] = slots.filter((s) => (s.status ?? 'scheduled') === 'scheduled');
    } finally {
        loadingSlots[eventId] = false;
    }
};

const loadAllSlots = () => Promise.all(themeParkStore.events.map((e) => loadSlotsFor(e.id)));

watch(date, () => {
    // A previously-picked slot may no longer be in the new date's list.
    Object.keys(selectedSlotIds).forEach((key) => delete selectedSlotIds[key]);
    loadAllSlots();
});

onMounted(async () => {
    await themeParkStore.fetchEvents();
    if (auth.isAuthenticated) themeParkStore.fetchMyBookings({ silent: true });

    maxPrice.value = priceCeiling.value;

    if (route.query.event) {
        focusedEventId.value = Number(route.query.event);
        expandedEvents[focusedEventId.value] = true;
    }

    await loadAllSlots();
});

const canBook = (event) => {
    const slot = selectedSlotFor(event);
    if (!slot) return false;
    const count = ticketCountFor(event);
    return count >= 1 && count <= slot.available_capacity;
};

const addToCart = (event) => {
    eventMessages[event.id] = null;

    const slot = selectedSlotFor(event);
    if (!slot) {
        eventMessages[event.id] = { type: 'error', text: 'Pick a time slot first.' };
        return;
    }
    if (!canBook(event)) {
        eventMessages[event.id] = { type: 'error', text: 'Adjust the ticket count to fit the slot capacity.' };
        return;
    }

    const ticketCount = ticketCountFor(event);
    const subtotal = ticketCount * event.price_per_ticket;

    cart.addItem({
        type: 'themepark',
        eventId: event.id,
        eventName: event.name,
        slotId: slot.id,
        slotDate: slot.slot_date?.slice(0, 10),
        slotTime: slot.slot_time,
        ticketCount,
        pricePerTicket: event.price_per_ticket,
        subtotal,
    });

    eventMessages[event.id] = { type: 'success', text: `Added ${ticketCount} ticket(s) to cart.` };
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <!-- Describes the page; the sticky search bar below reports the
                 current search, so the two don't say the same thing twice. -->
            <TPageHeader title="Theme Park" subtitle="Rides, shows and beach events" icon="sparkle" />
        </template>

        <div class="shell space-y-5 pb-6 pt-5">
            <PromotionsStrip category="themepark" />

            <!-- BOOKING PARAMETER, deliberately not a search bar - same
                 reasoning as the hotels page. The date decides which day's
                 slots exist at all, so it wears the accent tint with the
                 consequence written on. Ticket counts are NOT here: they're
                 picked on each event, beside the time slot they belong to. -->
            <div class="elevated sticky top-[4.25rem] z-20 rounded-xl border border-primary/25 bg-primary-soft">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 p-4">
                    <div class="min-w-[14rem] flex-1">
                        <p class="inline-flex items-center gap-2 text-sm font-semibold text-primary">
                            <TIcon name="calendar" :size="16" />
                            Your visit
                        </p>
                        <p class="mt-0.5 text-xs text-foreground-secondary">
                            Time slots and capacity below are for this day. Pick a time on any event to book it.
                        </p>
                    </div>
                    <div class="w-full sm:max-w-xs lg:w-64">
                        <TDatePicker v-model="date" :min="today" label="Date" />
                    </div>
                </div>
            </div>

            <div v-if="upcomingBookings.length" class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border bg-surface-sunken px-4 py-2.5 text-sm">
                <span class="inline-flex items-center gap-1.5 font-medium text-foreground">
                    <TIcon name="ticket" :size="15" class="text-primary" /> Already booked
                </span>
                <span
                    v-for="booking in upcomingBookings"
                    :key="booking.id"
                    class="text-foreground-secondary"
                >
                    {{ booking.slot?.event?.name }} ·
                    {{ formatDateTime(booking.slot?.slot_date, booking.slot?.slot_time) }}
                    ({{ booking.ticket_count }})
                </span>
                <router-link :to="{ name: 'trips', query: { tab: 'park' } }" class="ml-auto font-medium text-primary hover:underline">
                    View all
                </router-link>
            </div>

            <div class="grid gap-5 xl:grid-cols-[17rem_minmax(0,1fr)] xl:gap-7">
                <!-- Parked below the sticky search bar. Erring low on purpose:
                     the bar sits at z-20, so a rail that stuck too high would
                     slide under it rather than just leaving a gap. -->
                <div class="xl:sticky xl:top-[11rem] xl:max-h-[calc(100vh-12.5rem)] xl:self-start xl:overflow-y-auto">
                    <FilterRail :result-label="resultLabel" :active-count="activeFilterCount" @clear="clearFilters">
                        <template #search>
                            <TInput
                                v-model="search"
                                type="search"
                                placeholder="Ride, show or beach event"
                                aria-label="Search events"
                            >
                                <template #prefix><TIcon name="search" :size="16" /></template>
                            </TInput>
                        </template>

                        <FilterSection
                            title="Experience"
                            :hint="selectedTypes.length ? `${selectedTypes.length} selected` : 'Any'"
                        >
                            <div class="space-y-2.5">
                                <label
                                    v-for="type in TYPES"
                                    :key="type.key"
                                    class="flex cursor-pointer items-center gap-3 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 shrink-0 rounded border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                                        :checked="selectedTypes.includes(type.key)"
                                        @change="toggleIn(selectedTypes, type.key)"
                                    />
                                    <span class="flex-1 text-foreground">{{ type.label }}</span>
                                    <span class="text-xs text-foreground-muted">{{ typeCounts[type.key] ?? 0 }}</span>
                                </label>
                            </div>
                        </FilterSection>

                        <FilterSection
                            title="Time of day"
                            :hint="selectedBands.length ? `${selectedBands.length} selected` : 'Any'"
                        >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="band in BANDS"
                                    :key="band.key"
                                    type="button"
                                    class="rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors"
                                    :class="selectedBands.includes(band.key)
                                        ? 'border-primary bg-primary-soft text-primary'
                                        : 'bg-surface text-foreground-secondary hover:border-strong hover:text-foreground'"
                                    :title="band.hint"
                                    @click="toggleIn(selectedBands, band.key)"
                                >
                                    {{ band.label }}
                                </button>
                            </div>
                        </FilterSection>

                        <FilterSection
                            v-if="priceCeiling > 0"
                            title="Price per ticket"
                            :hint="priceIsCapped ? `Up to ${formatMoney(priceCap)}` : 'Any'"
                        >
                            <input
                                v-model.number="maxPrice"
                                type="range"
                                :min="0"
                                :max="priceCeiling"
                                :step="5"
                                class="w-full accent-primary"
                                aria-label="Maximum price per ticket"
                            />
                            <div class="mt-1 flex justify-between text-xs text-foreground-muted">
                                <span>{{ formatMoney(0) }}</span>
                                <span>{{ formatMoney(priceCeiling) }}</span>
                            </div>
                        </FilterSection>

                        <FilterSection title="Availability">
                            <TSwitch v-model="hideSoldOut" label="Only show events with seats" />
                        </FilterSection>

                        <FilterSection title="Sort by" :hint="SORTS.find((s) => s.key === sort)?.label">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="option in SORTS"
                                    :key="option.key"
                                    type="button"
                                    class="rounded-lg border px-2.5 py-1.5 text-xs font-medium transition-colors"
                                    :class="sort === option.key
                                        ? 'border-primary bg-primary-soft text-primary'
                                        : 'bg-surface text-foreground-secondary hover:border-strong hover:text-foreground'"
                                    @click="sort = option.key"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </FilterSection>
                    </FilterRail>
                </div>

                <div class="min-w-0 space-y-4">
                    <div v-if="focusedEvent" class="flex items-center gap-2 text-sm">
                        <span class="inline-flex items-center gap-2 rounded-lg bg-primary-soft py-1.5 pl-3 pr-1.5 font-medium text-primary">
                            Showing only {{ focusedEvent.name }}
                            <button
                                type="button"
                                class="rounded p-1 transition-colors hover:bg-primary/15"
                                aria-label="Show all events"
                                @click="focusedEventId = null"
                            >
                                <TIcon name="x" :size="13" />
                            </button>
                        </span>
                    </div>

                    <div v-if="themeParkStore.loading.events" class="space-y-4">
                        <div v-for="n in 3" :key="n" class="h-44 animate-pulse rounded-xl border bg-surface-hover" />
                    </div>
                    <TEmptyState
                        v-else-if="themeParkStore.events.length === 0"
                        title="No events available yet"
                        description="Rides, shows and beach events will be listed here once scheduled."
                        icon="calendar"
                    />
                    <TEmptyState
                        v-else-if="visibleEvents.length === 0"
                        title="Nothing matches these filters"
                        description="Widen the price range, clear a filter, or try a different date."
                        icon="search"
                    />

                    <template v-else>
                    <article
                        v-for="event in visibleEvents"
                        :key="event.id"
                        class="elevated overflow-hidden rounded-xl border bg-surface"
                    >
                        <!-- Header + teaser together are the whole click-to-expand surface.
                             The expanded panel below is a separate sibling on purpose: it's
                             full of its own interactive controls (slot buttons, stepper,
                             Add to Cart), and this way a click there simply never reaches the
                             toggle handler, rather than needing `.stop` on every one of them. -->
                        <div
                            role="button"
                            tabindex="0"
                            class="cursor-pointer transition-colors hover:bg-surface-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/40"
                            :aria-expanded="!!expandedEvents[event.id]"
                            :aria-controls="`event-panel-${event.id}`"
                            @click="toggleExpanded(event.id)"
                            @keydown.enter="toggleExpanded(event.id)"
                            @keydown.space.prevent="toggleExpanded(event.id)"
                        >
                            <div class="flex flex-col gap-4 p-4 sm:flex-row sm:gap-5 sm:p-5">
                                <div class="flex h-36 w-full shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface-hover text-xs text-foreground-muted sm:h-28 sm:w-44 lg:h-32 lg:w-52">
                                    <img v-if="event.image_url" :src="event.image_url" :alt="event.name" class="h-full w-full object-cover" />
                                    <span v-else>No image</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-semibold tracking-tight text-foreground">{{ event.name }}</h3>
                                        <TBadge variant="primary" class="shrink-0 capitalize">
                                            {{ event.type.replace('_', ' ') }}
                                        </TBadge>
                                    </div>
                                    <p class="mt-1.5 line-clamp-2 text-sm leading-relaxed text-foreground-secondary">{{ event.description }}</p>
                                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-foreground-muted">
                                        <span class="inline-flex items-center gap-1"><TIcon name="pin" :size="13" /> {{ event.location }}</span>
                                        <span class="inline-flex items-center gap-1"><TIcon name="clock" :size="13" /> {{ event.duration_minutes }} min</span>
                                        <span v-if="openSlotsFor(event).length" class="inline-flex items-center gap-1">
                                            <TIcon name="calendar" :size="13" />
                                            {{ openSlotsFor(event).length }} time{{ openSlotsFor(event).length === 1 ? '' : 's' }} left
                                        </span>
                                    </div>
                                </div>

                                <!-- The width the wider layout bought goes here: price stops
                                     being one more item in a metadata row and becomes a column
                                     you can compare down. -->
                                <div class="flex shrink-0 items-end justify-between gap-3 border-t pt-3 sm:w-36 sm:flex-col sm:items-end sm:justify-center sm:border-l sm:border-t-0 sm:pl-5 sm:pt-0 lg:w-40">
                                    <div class="sm:text-right">
                                        <p class="text-xl font-semibold tracking-tight text-foreground">
                                            {{ formatMoney(event.price_per_ticket) }}
                                        </p>
                                        <p class="text-xs text-foreground-muted">per ticket</p>
                                    </div>
                                    <span class="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-primary">
                                        {{ expandedEvents[event.id] ? 'Hide times' : 'Select a time' }}
                                        <TIcon name="chevronDown" :size="15" class="transition-transform" :class="expandedEvents[event.id] ? 'rotate-180' : ''" />
                                    </span>
                                </div>
                            </div>

                            <!-- Booking block is collapsed by default - with many events on the
                                 page, showing every slot picker eagerly was most of each card's
                                 height. The teaser keeps it from being a dead end when closed. -->
                            <div class="border-t px-4 py-2.5 text-sm text-foreground-secondary sm:px-5">
                                {{ slotsTeaser(event) }}
                            </div>
                        </div>

                        <div :id="`event-panel-${event.id}`" class="grid transition-[grid-template-rows] duration-200 ease-out" :style="{ gridTemplateRows: expandedEvents[event.id] ? '1fr' : '0fr' }">
                            <div class="overflow-hidden">
                                <div class="bg-surface-sunken/40 p-4 pt-4 sm:p-5">
                                    <div v-if="loadingSlots[event.id]" class="text-sm text-foreground-muted">Loading time slots...</div>
                                    <div
                                        v-else-if="slotsFor(event).length === 0"
                                        class="rounded-lg bg-surface-hover p-3 text-sm text-foreground-muted"
                                    >
                                        No time slots available for this date{{ selectedBands.length ? ' in the selected part of the day' : '' }}.
                                    </div>
                                    <!-- A time is a short token, so with the full-width layout the
                                         slots read far better as a wrapping grid than as the tall
                                         scroll list this used to be — you see the whole day at once
                                         instead of paging through it. Single-select, so each tile is
                                         a real radio input, the same native-radio treatment TRadio
                                         and every other selector in the app uses. -->
                                    <div v-else class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                                        <label
                                            v-for="slot in slotsFor(event)"
                                            :key="slot.id"
                                            class="flex items-center gap-2.5 rounded-lg border bg-surface p-2.5 transition-colors"
                                            :class="[
                                                selectedSlotIds[event.id] === slot.id ? 'border-primary bg-primary-soft' : '',
                                                slot.available_capacity < 1
                                                    ? 'cursor-not-allowed opacity-50'
                                                    : 'cursor-pointer hover:border-strong hover:bg-surface-hover',
                                            ]"
                                        >
                                            <input
                                                type="radio"
                                                :name="`event-${event.id}-slot`"
                                                :checked="selectedSlotIds[event.id] === slot.id"
                                                :disabled="slot.available_capacity < 1"
                                                class="h-4 w-4 shrink-0 border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                                                @change="selectSlot(event, slot)"
                                            />
                                            <span class="min-w-0">
                                                <span
                                                    class="block text-sm font-semibold"
                                                    :class="selectedSlotIds[event.id] === slot.id ? 'text-primary' : 'text-foreground'"
                                                >
                                                    {{ formatTime(slot.slot_time) }}
                                                </span>
                                                <span
                                                    class="block text-xs"
                                                    :class="slot.available_capacity < 1
                                                        ? 'text-foreground-muted'
                                                        : slot.available_capacity > 2 ? 'text-success' : 'text-warning'"
                                                >
                                                    {{ slot.available_capacity < 1 ? 'Full' : `${slot.available_capacity} left` }}
                                                </span>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t pt-4">
                                        <p
                                            v-if="eventMessages[event.id]"
                                            class="text-sm"
                                            :class="eventMessages[event.id].type === 'error' ? 'text-danger' : 'text-success'"
                                        >
                                            {{ eventMessages[event.id].text }}
                                            <router-link
                                                v-if="eventMessages[event.id].type === 'success'"
                                                :to="{ name: 'hotels.index' }"
                                                class="font-medium underline"
                                            >
                                                Book a hotel for the stay?
                                            </router-link>
                                        </p>
                                        <p v-else-if="!selectedSlotFor(event)" class="text-xs text-foreground-muted">
                                            Pick a time above to add tickets
                                        </p>
                                        <p v-else class="text-sm text-foreground-secondary">
                                            {{ ticketCountFor(event) }} × {{ formatMoney(event.price_per_ticket) }} =
                                            <span class="font-semibold text-foreground">
                                                {{ formatMoney(ticketCountFor(event) * event.price_per_ticket) }}
                                            </span>
                                        </p>

                                        <!-- The one tickets control, and it lives here: counts
                                             are decided per attraction, next to the time they're
                                             for. The stepper's ceiling is the chosen slot's real
                                             capacity. -->
                                        <div class="ml-auto flex items-center gap-3">
                                            <TNumberInput
                                                label="Tickets"
                                                label-position="left"
                                                :model-value="ticketCountFor(event)"
                                                @update:model-value="v => setTicketCount(event, v)"
                                                :min="1"
                                                :max="selectedSlotFor(event)?.available_capacity"
                                                size="sm"
                                            />
                                            <TButton :disabled="!canBook(event)" @click="addToCart(event)">
                                                Add to itinerary
                                            </TButton>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
