<script setup>
import { computed, onMounted, reactive } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TimelineIcon from '@/Components/icons/TimelineIcon.vue';
import { useAuthStore } from '@/stores/auth';
import { useHotelStore } from '@/stores/hotel';
import { useFerryStore } from '@/stores/ferry';
import { useThemeParkStore } from '@/stores/themepark';

const auth = useAuthStore();
const hotelStore = useHotelStore();
const ferryStore = useFerryStore();
const themeParkStore = useThemeParkStore();

const isVisitor = computed(() => auth.userRole === 'visitor');
const today = new Date().toISOString().slice(0, 10);

onMounted(() => {
    if (isVisitor.value) {
        hotelStore.fetchMyBookings();
        ferryStore.fetchMyTickets();
        themeParkStore.fetchMyBookings();
    }
});

// A ferry ticket's own record has no notion of direction - it's inferred by
// comparing its departure date against the linked hotel booking's stay dates
// (the booking flow only ever sells departures on check-in or check-out day).
// Drives both the arrow icon and same-day sort ties (arrive by ferry before
// checking into the hotel, check out of the hotel before the ferry back).
const ferryDirection = (ticket) => {
    const departureDate = (ticket.schedule?.departure_date ?? '').slice(0, 10);
    if (departureDate && departureDate === (ticket.booking?.check_out_date ?? '').slice(0, 10)) return 'back';
    return 'out';
};

// Groups a flat list of raw rows sharing the same groupKey (same hotel/ferry/
// event AND same date+time) into one summary row with a `children` list -
// e.g. a 2-room hotel purchase or a multi-seat ferry ticket set collapses
// into one row instead of listing each room/seat separately.
const groupItems = (rows) => {
    const groups = new Map();
    for (const row of rows) {
        if (!groups.has(row.groupKey)) groups.set(row.groupKey, []);
        groups.get(row.groupKey).push(row);
    }
    return Array.from(groups.values()).map((children) => ({
        ...children[0],
        key: `group-${children[0].groupKey}`,
        children,
    }));
};

// Dashboard only ever shows what's still relevant — past bookings live in
// each module's dedicated "my bookings" page, not here.
const upcomingItems = computed(() => {
    const hotel = groupItems(
        hotelStore.myBookings
            .filter((b) => b.status !== 'cancelled' && (b.check_out_date ?? '').slice(0, 10) >= today)
            .map((b) => ({
                key: `hotel-${b.id}`,
                id: b.id,
                groupKey: `hotel-${b.room?.hotel?.id}-${b.check_in_date?.slice(0, 10)}-${b.check_out_date?.slice(0, 10)}`,
                icon: 'hotel',
                group: 'hotel',
                type: 'Hotel',
                sequence: 1,
                title: b.room?.hotel?.name,
                childLabel: `${b.reference_code} - ${b.guests_count} guest${b.guests_count === 1 ? '' : 's'}`,
                detail: `${b.check_in_date?.slice(0, 10)} to ${b.check_out_date?.slice(0, 10)}`,
                date: (b.check_in_date ?? '').slice(0, 10),
                to: { name: 'bookings.confirm', query: { ids: String(b.id) } },
            }))
    ).map((g) => ({
        ...g,
        detail: g.children.length > 1 ? `${g.children.length} room(s), ${g.detail}` : g.detail,
        to: { name: 'bookings.confirm', query: { ids: g.children.map((c) => c.id).join(',') } },
    }));

    const ferry = groupItems(
        ferryStore.myTickets
            .filter((t) => t.status === 'issued' && (t.schedule?.departure_date ?? '').slice(0, 10) >= today)
            .map((t) => {
                const direction = ferryDirection(t);
                return {
                    key: `ferry-${t.id}`,
                    groupKey: `ferry-${t.schedule_id}`,
                    icon: direction === 'back' ? 'ferry-back' : 'ferry-out',
                    group: 'ferry',
                    type: 'Ferry',
                    sequence: direction === 'back' ? 3 : 0,
                    title: t.schedule?.ferry?.name,
                    childLabel: `Seat ${t.seat_number}`,
                    detail: `${t.schedule?.departure_date?.slice(0, 10)} at ${t.schedule?.departure_time}`,
                    date: (t.schedule?.departure_date ?? '').slice(0, 10),
                    to: { name: 'ferry.my-tickets' },
                };
            })
    ).map((g) => ({
        ...g,
        detail: `${g.detail} (${g.children.length} seat${g.children.length === 1 ? '' : 's'})`,
    }));

    const themepark = groupItems(
        themeParkStore.myBookings
            .filter((b) => b.status === 'confirmed' && (b.slot?.slot_date ?? '').slice(0, 10) >= today)
            .map((b) => ({
                key: `themepark-${b.id}`,
                groupKey: `themepark-${b.event_slot_id}`,
                icon: 'themepark',
                group: 'themepark',
                type: 'Theme Park',
                sequence: 2,
                title: b.slot?.event?.name,
                childLabel: `${b.ticket_count} ticket(s)`,
                detail: `${b.slot?.slot_date?.slice(0, 10)} at ${b.slot?.slot_time}`,
                date: (b.slot?.slot_date ?? '').slice(0, 10),
                to: { name: 'themepark.my-bookings' },
                ticketCount: b.ticket_count,
            }))
    ).map((g) => ({
        ...g,
        detail: `${g.detail} (${g.children.reduce((sum, c) => sum + c.ticketCount, 0)} ticket(s))`,
    }));

    return [...hotel, ...ferry, ...themepark].sort((a, b) => a.date.localeCompare(b.date) || a.sequence - b.sequence);
});

const expandedKeys = reactive(new Set());
const toggleExpanded = (key) => {
    if (expandedKeys.has(key)) expandedKeys.delete(key);
    else expandedKeys.add(key);
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <template v-if="isVisitor">
                    <h3 class="mb-4 font-semibold text-gray-900">Coming Up</h3>

                    <div v-if="upcomingItems.length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">
                        Nothing coming up yet. Browse hotels, ferry schedules, or theme park events to get started.
                    </div>

                    <div v-else class="space-y-3">
                        <div v-for="item in upcomingItems" :key="item.key" class="rounded-lg bg-white shadow-sm hover:shadow-md">
                            <router-link :to="item.to" class="flex items-center justify-between p-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <TimelineIcon
                                            :type="item.icon"
                                            :class="{
                                                'text-green-600': item.group === 'hotel',
                                                'text-yellow-600': item.group === 'ferry',
                                                'text-blue-600': item.group === 'themepark',
                                            }"
                                        />
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="{
                                                'bg-green-100 text-green-800': item.group === 'hotel',
                                                'bg-yellow-100 text-yellow-800': item.group === 'ferry',
                                                'bg-blue-100 text-blue-800': item.group === 'themepark',
                                            }"
                                        >
                                            {{ item.type }}
                                        </span>
                                        <p class="font-semibold text-gray-900">{{ item.title }}</p>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">{{ item.detail }}</p>
                                </div>

                                <button
                                    v-if="item.children.length > 1"
                                    type="button"
                                    @click.stop.prevent="toggleExpanded(item.key)"
                                    class="shrink-0 rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                                >
                                    <svg
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                        class="h-4 w-4 transition-transform"
                                        :class="{ 'rotate-180': expandedKeys.has(item.key) }"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                                    </svg>
                                </button>
                            </router-link>

                            <div v-if="item.children.length > 1 && expandedKeys.has(item.key)" class="space-y-1 border-t border-gray-100 px-4 py-2">
                                <p v-for="child in item.children" :key="child.key" class="text-sm text-gray-500">
                                    {{ child.childLabel }}
                                </p>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-else class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        You're logged in!
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
