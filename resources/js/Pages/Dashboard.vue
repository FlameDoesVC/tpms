<script setup>
import { computed, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TimelineIcon from '@/Components/icons/TimelineIcon.vue';
import { useAuthStore } from '@/stores/auth';
import { useHotelStore } from '@/stores/hotel';
import { useFerryStore } from '@/stores/ferry';
import { useThemeParkStore } from '@/stores/themepark';

const auth = useAuthStore();
const router = useRouter();
if (auth.userRole === 'admin') {
    router.replace({ name: 'admin.dashboard' });
}

const STAFF_ROLES = ['hotel_manager', 'ferry_operator', 'themepark_staff', 'admin'];
const layout = computed(() => (STAFF_ROLES.includes(auth.userRole) ? StaffLayout : AuthenticatedLayout));
const isStaff = computed(() => STAFF_ROLES.includes(auth.userRole));
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

const ferryDirection = (ticket) => {
    const departureDate = (ticket.schedule?.departure_date ?? '').slice(0, 10);
    if (departureDate && departureDate === (ticket.booking?.check_out_date ?? '').slice(0, 10)) return 'back';
    return 'out';
};

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

const badgeVariant = { hotel: 'success', ferry: 'warning', themepark: 'info' };
const iconColor = { hotel: 'text-success', ferry: 'text-warning', themepark: 'text-info' };

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
    <component :is="layout">
        <template #header>
            <TPageHeader :compact="isStaff" title="Dashboard" icon="dashboard" />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <template v-if="isVisitor">
                    <h3 class="mb-4 font-semibold text-foreground">Coming Up</h3>

                    <TEmptyState
                        v-if="upcomingItems.length === 0"
                        title="Nothing coming up"
                        description="Browse hotels, ferry schedules, or theme park events to get started."
                        icon="calendar"
                    />

                    <div v-else class="space-y-3">
                        <div v-for="item in upcomingItems" :key="item.key" class="overflow-hidden rounded-xl border bg-surface transition-shadow hover:shadow-md">
                            <router-link :to="item.to" class="flex items-center justify-between p-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <TimelineIcon :type="item.icon" :class="iconColor[item.group]" />
                                        <TBadge :variant="badgeVariant[item.group]">{{ item.type }}</TBadge>
                                        <p class="font-semibold text-foreground">{{ item.title }}</p>
                                    </div>
                                    <p class="mt-1 text-sm text-foreground-muted">{{ item.detail }}</p>
                                </div>

                                <button
                                    v-if="item.children.length > 1"
                                    type="button"
                                    @click.stop.prevent="toggleExpanded(item.key)"
                                    class="shrink-0 rounded-lg p-1.5 text-foreground-muted hover:bg-surface-hover hover:text-foreground"
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

                            <div v-if="item.children.length > 1 && expandedKeys.has(item.key)" class="space-y-1 border-t px-4 py-2">
                                <p v-for="child in item.children" :key="child.key" class="text-sm text-foreground-muted">
                                    {{ child.childLabel }}
                                </p>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-else class="overflow-hidden rounded-xl border bg-surface">
                    <div class="p-6 text-foreground">
                        You're logged in!
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
