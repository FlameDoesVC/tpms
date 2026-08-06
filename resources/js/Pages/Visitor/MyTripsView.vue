<script setup>
/**
 * My Trips.
 *
 * This was three product tabs - Stays, Ferry, Park - and nothing else. That reads
 * fine until you have an actual trip: a stay from the 12th to the 15th with an
 * outbound crossing on the 12th, park time on the 13th and a return on the 15th
 * arrived as five cards spread across three tabs, and you rejoined them yourself
 * by reading dates. Meanwhile the landing page had already promised "one trip,
 * three legs - chosen together, paid together".
 *
 * So trips are the default view and product type is the secondary one. The type
 * lists are kept rather than deleted: they own the per-ticket actions - QR for
 * boarding, save-to-file - that a trip summary has no room for.
 */
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TButton from '@/Components/ui/TButton.vue';
import TripCard from '@/Pages/Visitor/Trips/TripCard.vue';
import HotelStaysTab from '@/Pages/Visitor/Trips/HotelStaysTab.vue';
import FerryTicketsTab from '@/Pages/Visitor/Trips/FerryTicketsTab.vue';
import ParkTicketsTab from '@/Pages/Visitor/Trips/ParkTicketsTab.vue';
import { useHotelStore } from '@/stores/hotel';
import { useFerryStore } from '@/stores/ferry';
import { useThemeParkStore } from '@/stores/themepark';
import { formatDate, formatMoney, formatTime, todayIso, toIsoDate } from '@/utils/format';

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();
const ferryStore = useFerryStore();
const themeParkStore = useThemeParkStore();

const TABS = [
    { key: 'hotel', label: 'Stays', icon: 'hotel', component: HotelStaysTab },
    { key: 'ferry', label: 'Ferry', icon: 'ferry', component: FerryTicketsTab },
    { key: 'park', label: 'Park', icon: 'ticket', component: ParkTicketsTab },
];

/* ------------------------------- view state ------------------------------- */

// Both view and tab live in the query, so a list stays linkable and survives a
// refresh - the checkout receipt deep-links straight to a tab.
const view = computed(() => (route.query.view === 'type' ? 'type' : 'trips'));
const activeKey = computed(() => {
    const requested = String(route.query.tab ?? '');
    return TABS.some((t) => t.key === requested) ? requested : 'hotel';
});
const activeTab = computed(() => TABS.find((t) => t.key === activeKey.value) ?? TABS[0]);

const setView = (next) => {
    if (next === view.value) return;
    router.replace({ name: 'trips', query: { ...route.query, view: next === 'trips' ? undefined : next } });
};
const selectTab = (key) => {
    if (key === activeKey.value) return;
    router.replace({ name: 'trips', query: { ...route.query, tab: key } });
};

// A trip needs all three sources at once, not just the visible tab's - the old
// page could get away with lazy per-tab loading, this one cannot.
onMounted(() => {
    if (!hotelStore.myBookings.length) hotelStore.fetchMyBookings({ silent: true });
    if (!ferryStore.myTickets.length) ferryStore.fetchMyTickets({ silent: true });
    if (!themeParkStore.myBookings.length) themeParkStore.fetchMyBookings({ silent: true });
});

/* --------------------------------- legs ---------------------------------- */

const ferryLeg = (ticket) => ({
    kind: 'ferry',
    key: `ferry-${ticket.id}`,
    date: ticket.schedule?.departure_date,
    time: ticket.schedule?.departure_time,
    title: ticket.schedule?.ferry?.name ?? 'Ferry crossing',
    detail: ticket.seat_number ? `Seat ${ticket.seat_number}` : null,
    reference: ticket.reference_code,
    status: ticket.status,
    price: ticket.price,
});

const parkLeg = (booking) => ({
    kind: 'park',
    key: `park-${booking.id}`,
    date: booking.slot?.slot_date,
    time: booking.slot?.slot_time,
    title: booking.slot?.event?.name ?? 'Park event',
    detail: `${booking.ticket_count} ticket${booking.ticket_count === 1 ? '' : 's'}`,
    reference: booking.reference_code,
    status: booking.status,
    price: booking.slot?.event?.price_per_ticket
        ? Number(booking.slot.event.price_per_ticket) * booking.ticket_count
        : null,
});

const byMoment = (a, b) =>
    `${toIsoDate(a.date)}${a.time ?? ''}`.localeCompare(`${toIsoDate(b.date)}${b.time ?? ''}`);

/**
 * Stays, each with the legs that belong to it.
 *
 * Crossings attach by foreign key - ferry_tickets.booking_id, which exists
 * because the server refuses a seat without a stay. Park slots have no such
 * column, so they attach by falling inside the stay's dates. The first is a
 * fact, the second an inference, which is why anything left unclaimed is shown
 * on its own rather than forced into a trip it may not belong to.
 */
const grouped = computed(() => {
    const claimedTickets = new Set();
    const claimedPark = new Set();

    const trips = hotelStore.myBookings.map((stay) => {
        const from = toIsoDate(stay.check_in_date);
        const to = toIsoDate(stay.check_out_date);
        const legs = [];

        for (const ticket of ferryStore.myTickets) {
            if (ticket.booking_id === stay.id) {
                claimedTickets.add(ticket.id);
                legs.push(ferryLeg(ticket));
            }
        }
        for (const booking of themeParkStore.myBookings) {
            const day = booking.slot?.slot_date ? toIsoDate(booking.slot.slot_date) : null;
            if (day && day >= from && day <= to) {
                claimedPark.add(booking.id);
                legs.push(parkLeg(booking));
            }
        }

        legs.sort(byMoment);
        return { key: `stay-${stay.id}`, stay, legs, from, to };
    });

    const loose = [
        ...ferryStore.myTickets.filter((t) => !claimedTickets.has(t.id)).map(ferryLeg),
        ...themeParkStore.myBookings.filter((b) => !claimedPark.has(b.id)).map(parkLeg),
    ].sort(byMoment);

    return { trips, loose };
});

const today = todayIso();

const buckets = computed(() => {
    const upcoming = [];
    const past = [];
    const cancelled = [];

    for (const trip of grouped.value.trips) {
        if (trip.stay.status === 'cancelled') cancelled.push(trip);
        else if (trip.to < today) past.push(trip);
        else upcoming.push(trip);
    }

    upcoming.sort((a, b) => a.from.localeCompare(b.from));
    past.sort((a, b) => b.from.localeCompare(a.from));
    cancelled.sort((a, b) => b.from.localeCompare(a.from));
    return { upcoming, past, cancelled };
});

const GROUPS = computed(() => [
    { key: 'upcoming', label: 'Upcoming', trips: buckets.value.upcoming },
    { key: 'past', label: 'Past', trips: buckets.value.past },
    { key: 'cancelled', label: 'Cancelled', trips: buckets.value.cancelled },
]);

/* -------------------------------- summary -------------------------------- */

const nextTrip = computed(() => buckets.value.upcoming[0] ?? null);
const daysToNext = computed(() => {
    if (!nextTrip.value) return null;
    const ms = new Date(`${nextTrip.value.from}T00:00:00`) - new Date(`${today}T00:00:00`);
    return Math.max(0, Math.round(ms / 86400000));
});
const amountDue = computed(() =>
    hotelStore.myBookings
        .filter((b) => b.status === 'pending')
        .reduce((sum, b) => sum + (Number(b.total_price) || 0), 0),
);
const loading = computed(() => hotelStore.loading?.bookings && !hotelStore.myBookings.length);

/**
 * The next concrete things on the calendar, flattened across trips: check-ins,
 * crossings, park slots, in the order they happen. A trip card answers "what
 * does this trip contain"; this answers "what do I do next".
 */
const agenda = computed(() => {
    const items = [];
    for (const trip of buckets.value.upcoming) {
        if (trip.from >= today) {
            items.push({
                key: `checkin-${trip.key}`,
                kind: 'stay',
                date: trip.from,
                time: null,
                title: `Check in · ${trip.stay.room?.hotel?.name ?? 'hotel'}`,
            });
        }
        for (const leg of trip.legs) {
            if (toIsoDate(leg.date) >= today) items.push(leg);
        }
    }
    items.sort(byMoment);
    return items.slice(0, 6);
});

const AGENDA_ICON = { stay: 'hotel', ferry: 'ferry', park: 'sparkle' };

/** Jump to a time bucket. The rail is sticky, so this is real navigation. */
const jumpTo = (key) => {
    const el = document.getElementById(`trips-${key}`);
    if (!el) return;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    el.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
};

const counts = computed(() => ({
    hotel: hotelStore.myBookings.filter((b) => b.status !== 'cancelled').length,
    ferry: ferryStore.myTickets.filter((t) => t.status !== 'cancelled').length,
    park: themeParkStore.myBookings.filter((b) => b.status !== 'cancelled').length,
}));
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader title="My Trips" subtitle="Stays, crossings and park tickets in one place." icon="ticket" />
        </template>

        <div class="shell pb-6 pt-5">
            <div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <!-- ------------------------------ rail ------------------------------
                 Sticky, so "payment due" is still on screen when the trip that
                 owes it is. The stats used to sit in a strip ABOVE the list,
                 which pushed the first trip half a screen down and then
                 scrolled away exactly when they were needed. -->
            <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
                <div class="elevated rounded-xl border bg-surface p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-foreground-muted">Next trip</p>
                    <p v-if="nextTrip" class="mt-1 text-lg font-semibold tracking-tight text-foreground">
                        {{ daysToNext === 0 ? 'Today' : `In ${daysToNext} day${daysToNext === 1 ? '' : 's'}` }}
                    </p>
                    <p v-else class="mt-1 text-lg font-semibold tracking-tight text-foreground-muted">—</p>
                    <p v-if="nextTrip" class="mt-0.5 truncate text-sm text-foreground-secondary">
                        {{ nextTrip.stay.room?.hotel?.name }} · {{ formatDate(nextTrip.from) }}
                    </p>
                    <p v-else class="mt-0.5 text-sm text-foreground-secondary">Nothing booked ahead</p>
                    <p v-if="nextTrip" class="mt-2 text-xs text-foreground-muted">
                        {{ nextTrip.legs.length }} leg{{ nextTrip.legs.length === 1 ? '' : 's' }} booked
                    </p>
                </div>

                <div
                    v-if="amountDue"
                    class="elevated rounded-xl border border-warning/40 bg-warning-soft/40 p-4"
                >
                    <p class="text-xs font-semibold uppercase tracking-wider text-foreground-muted">Payment due</p>
                    <p class="mt-1 text-lg font-semibold tracking-tight text-warning">{{ formatMoney(amountDue) }}</p>
                    <p class="mt-0.5 text-sm text-foreground-secondary">Pay before check-in to keep the room</p>
                </div>

                <div v-if="agenda.length" class="elevated rounded-xl border bg-surface p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-foreground-muted">Coming up</p>
                    <ol class="mt-3 space-y-2.5">
                        <li v-for="item in agenda" :key="item.key" class="flex items-start gap-2.5">
                            <span
                                class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full"
                                :class="item.kind === 'ferry' ? 'bg-accent/10 text-accent'
                                    : item.kind === 'park' ? 'bg-primary-soft text-primary'
                                    : 'bg-surface-hover text-foreground-secondary'"
                            >
                                <TIcon :name="AGENDA_ICON[item.kind] ?? 'calendar'" :size="14" />
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-foreground">{{ item.title }}</span>
                                <span class="block text-xs text-foreground-muted">
                                    {{ formatDate(item.date) }}<template v-if="item.time"> · {{ formatTime(item.time) }}</template>
                                </span>
                            </span>
                        </li>
                    </ol>
                </div>

                <!-- The time buckets as navigation instead of scroll-past
                     headings; with a sticky rail this scales past the point
                     where four trips become forty. -->
                <nav v-if="view === 'trips' && grouped.trips.length" class="elevated rounded-xl border bg-surface p-2">
                    <button
                        v-for="group in GROUPS.filter((g) => g.trips.length)"
                        :key="group.key"
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-sm font-medium text-foreground-secondary transition-colors hover:bg-surface-hover hover:text-foreground"
                        @click="jumpTo(group.key)"
                    >
                        {{ group.label }}
                        <span class="rounded-full bg-surface-hover px-1.5 text-xs font-semibold text-foreground-secondary">
                            {{ group.trips.length }}
                        </span>
                    </button>
                </nav>

                <router-link
                    :to="{ name: 'hotels.index' }"
                    class="flex items-center justify-center gap-1.5 rounded-xl border px-3 py-2.5 text-sm font-medium text-foreground-secondary transition-colors hover:bg-surface-hover hover:text-foreground"
                >
                    <TIcon name="plus" :size="15" />
                    Plan another trip
                </router-link>
            </aside>

            <!-- ----------------------------- content -----------------------------
                 Full width, no cap: the system's convention is that content
                 fills the screen where it can, and the trip cards are built to
                 earn that width - their legs flow as tiles across the row
                 rather than stacking into stretched full-width lines. -->
            <div class="min-w-0 space-y-5">
            <!-- Trips first, product type second. -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-1 rounded-lg border bg-surface p-1" role="tablist">
                    <button
                        v-for="option in [
                            { key: 'trips', label: 'By trip', icon: 'calendar' },
                            { key: 'type', label: 'By type', icon: 'inbox' },
                        ]"
                        :key="option.key"
                        type="button"
                        role="tab"
                        :aria-selected="view === option.key"
                        class="inline-flex items-center gap-2 rounded px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="view === option.key
                            ? 'bg-primary-soft text-primary'
                            : 'text-foreground-secondary hover:bg-surface-hover hover:text-foreground'"
                        @click="setView(option.key)"
                    >
                        <TIcon :name="option.icon" :size="15" />
                        {{ option.label }}
                    </button>
                </div>

            </div>

            <!-- ------------------------------ BY TRIP ------------------------------ -->
            <template v-if="view === 'trips'">
                <div v-if="loading" class="space-y-4">
                    <div v-for="n in 2" :key="n" class="h-44 animate-pulse rounded-xl border bg-surface-hover" />
                </div>

                <TEmptyState
                    v-else-if="!grouped.trips.length && !grouped.loose.length"
                    title="No trips yet"
                    description="A stay comes first — crossings and park time are booked against it."
                    icon="ticket"
                >
                    <template #action>
                        <router-link :to="{ name: 'hotels.index' }">
                            <TButton as="span">Find a room</TButton>
                        </router-link>
                    </template>
                </TEmptyState>

                <template v-else>
                    <section v-for="group in GROUPS" :id="`trips-${group.key}`" :key="group.key" class="scroll-mt-24">
                        <template v-if="group.trips.length">
                            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                {{ group.label }}
                                <span class="ml-1 font-normal">({{ group.trips.length }})</span>
                            </h2>
                            <div class="space-y-4">
                                <TripCard
                                    v-for="trip in group.trips"
                                    :key="trip.key"
                                    :stay="trip.stay"
                                    :legs="trip.legs"
                                    :tense="group.key"
                                />
                            </div>
                        </template>
                    </section>

                    <!-- Bookings that sit inside no stay. Shown rather than
                         hidden: a crossing with nothing attached is exactly the
                         thing a visitor would otherwise believe was lost. -->
                    <section v-if="grouped.loose.length">
                        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                            Not part of a stay
                            <span class="ml-1 font-normal">({{ grouped.loose.length }})</span>
                        </h2>
                        <ul class="elevated divide-y overflow-hidden rounded-xl border bg-surface">
                            <li
                                v-for="leg in grouped.loose"
                                :key="leg.key"
                                class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3"
                            >
                                <span
                                    class="grid h-9 w-9 shrink-0 place-items-center rounded-full"
                                    :class="leg.kind === 'ferry' ? 'bg-accent/10 text-accent' : 'bg-primary-soft text-primary'"
                                >
                                    <TIcon :name="leg.kind === 'ferry' ? 'ferry' : 'sparkle'" :size="17" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-foreground">{{ leg.title }}</span>
                                    <span class="text-xs text-foreground-muted">
                                        {{ formatDate(leg.date) }}<template v-if="leg.detail"> · {{ leg.detail }}</template>
                                    </span>
                                </span>
                                <span class="text-sm tabular-nums text-foreground-secondary">
                                    {{ leg.price ? formatMoney(leg.price) : '—' }}
                                </span>
                            </li>
                        </ul>
                    </section>
                </template>
            </template>

            <!-- ------------------------------ BY TYPE ------------------------------ -->
            <template v-else>
                <div class="flex gap-1 overflow-x-auto rounded-xl border bg-surface p-1" role="tablist">
                    <button
                        v-for="tab in TABS"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeKey === tab.key"
                        class="inline-flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                        :class="activeKey === tab.key
                            ? 'bg-primary-soft text-primary'
                            : 'text-foreground-secondary hover:bg-surface-hover hover:text-foreground'"
                        @click="selectTab(tab.key)"
                    >
                        <TIcon :name="tab.icon" :size="16" />
                        {{ tab.label }}
                        <span
                            v-if="counts[tab.key]"
                            class="rounded-full bg-surface-hover px-1.5 text-xs font-semibold text-foreground-secondary"
                        >
                            {{ counts[tab.key] }}
                        </span>
                    </button>
                </div>

                <!-- Kept alive so switching tabs doesn't refetch, and a revealed
                     QR or a selection isn't thrown away mid-task. -->
                <KeepAlive>
                    <component :is="activeTab.component" :key="activeTab.key" />
                </KeepAlive>
            </template>
            </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
