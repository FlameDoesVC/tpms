<script setup>
import { computed, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TimelineIcon from '@/Components/icons/TimelineIcon.vue';
import { formatDateRange, formatDateTime, todayIso } from '@/utils/format';
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
const today = todayIso();

const loadTrips = () => {
    hotelStore.fetchMyBookings();
    ferryStore.fetchMyTickets();
    themeParkStore.fetchMyBookings();
};

onMounted(() => {
    if (isVisitor.value) loadTrips();
});

// Without this the empty state renders for the split second before any of the
// three requests resolve, so every load flashed "Nothing coming up".
const loading = computed(() =>
    hotelStore.loading.bookings || ferryStore.loading.tickets || themeParkStore.loading.bookings
);

// A failed fetch is otherwise indistinguishable from having no plans.
const loadError = computed(() =>
    hotelStore.error.bookings || ferryStore.error.tickets || themeParkStore.error.bookings
);

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
                childLabel: `${b.reference_code} · ${b.guests_count} guest${b.guests_count === 1 ? '' : 's'}`,
                detail: formatDateRange(b.check_in_date, b.check_out_date),
                date: (b.check_in_date ?? '').slice(0, 10),
                pending: b.status === 'pending',
            }))
    ).map((g) => {
        const pendingIds = g.children.filter((c) => c.pending).map((c) => c.id);
        return {
            ...g,
            detail: g.children.length > 1 ? `${g.children.length} rooms · ${g.detail}` : g.detail,
            pending: pendingIds.length > 0,
            to: pendingIds.length
                ? { name: 'bookings.confirm', query: { ids: pendingIds.join(',') } }
                : { name: 'trips', query: { tab: 'hotel' } },
        };
    });

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
                    detail: formatDateTime(t.schedule?.departure_date, t.schedule?.departure_time),
                    date: (t.schedule?.departure_date ?? '').slice(0, 10),
                    to: { name: 'trips', query: { tab: 'ferry' } },
                };
            })
    ).map((g) => ({
        ...g,
        detail: `${g.detail} · ${g.children.length} seat${g.children.length === 1 ? '' : 's'}`,
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
                detail: formatDateTime(b.slot?.slot_date, b.slot?.slot_time),
                date: (b.slot?.slot_date ?? '').slice(0, 10),
                to: { name: 'trips', query: { tab: 'park' } },
                ticketCount: b.ticket_count,
            }))
    ).map((g) => ({
        ...g,
        detail: `${g.detail} · ${g.children.reduce((sum, c) => sum + c.ticketCount, 0)} ticket(s)`,
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

        <!-- This page renders under either layout depending on role, and the two
             differ in who owns the padding: StaffLayout's <main> is already the
             page container, AuthenticatedLayout's is bare. Supplying it
             unconditionally gave staff two sets of gutters stacked. -->
        <div :class="isStaff ? '' : 'shell py-6'">
            <div class="mx-auto max-w-5xl">
                <template v-if="isVisitor">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-foreground">Coming Up</h3>
                        <router-link :to="{ name: 'trips' }" class="text-sm font-medium text-primary hover:underline">
                            View all trips
                        </router-link>
                    </div>

                    <div v-if="loading" class="space-y-3">
                        <div v-for="n in 2" :key="n" class="h-20 animate-pulse rounded-xl border bg-surface-hover" />
                    </div>

                    <div
                        v-else-if="loadError"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-danger bg-danger-soft p-4 text-sm text-danger"
                    >
                        <span>{{ loadError }}</span>
                        <TButton size="sm" variant="secondary" @click="loadTrips">Try again</TButton>
                    </div>

                    <TEmptyState
                        v-else-if="upcomingItems.length === 0"
                        title="Nothing coming up"
                        description="Browse hotels, ferry schedules, or theme park events to get started."
                        icon="calendar"
                    >
                        <template #action>
                            <div class="flex flex-wrap justify-center gap-2">
                                <router-link :to="{ name: 'hotels.index' }">
                                    <TButton size="sm">Browse hotels</TButton>
                                </router-link>
                                <router-link :to="{ name: 'themepark.home' }">
                                    <TButton variant="secondary" size="sm">Browse events</TButton>
                                </router-link>
                            </div>
                        </template>
                    </TEmptyState>

                    <div v-else class="space-y-3">
                        <!-- The chevron is a sibling of the link, not nested
                             inside it: a <button> inside an <a> is invalid and
                             leaves the two targets overlapping on touch. -->
                        <div
                            v-for="item in upcomingItems"
                            :key="item.key"
                            class="elevated overflow-hidden rounded-xl border bg-surface"
                        >
                            <div class="flex items-center gap-2 pr-3 transition-colors hover:bg-surface-hover">
                                <router-link :to="item.to" class="min-w-0 flex-1 p-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <TimelineIcon :type="item.icon" :class="iconColor[item.group]" />
                                        <TBadge :variant="badgeVariant[item.group]">{{ item.type }}</TBadge>
                                        <p class="font-semibold text-foreground">{{ item.title }}</p>
                                        <TBadge v-if="item.pending" variant="warning" dot>payment due</TBadge>
                                    </div>
                                    <p class="mt-1 text-sm text-foreground-muted">{{ item.detail }}</p>
                                </router-link>

                                <button
                                    v-if="item.children.length > 1"
                                    type="button"
                                    class="shrink-0 rounded-lg p-1.5 text-foreground-muted hover:bg-surface hover:text-foreground"
                                    :aria-expanded="expandedKeys.has(item.key)"
                                    :aria-label="`Show the ${item.children.length} items in ${item.title}`"
                                    @click="toggleExpanded(item.key)"
                                >
                                    <TIcon
                                        name="chevronDown"
                                        :size="16"
                                        class="transition-transform"
                                        :class="{ 'rotate-180': expandedKeys.has(item.key) }"
                                    />
                                </button>
                            </div>

                            <div v-if="item.children.length > 1 && expandedKeys.has(item.key)" class="space-y-1 border-t px-4 py-2">
                                <p v-for="child in item.children" :key="child.key" class="text-sm text-foreground-muted">
                                    {{ child.childLabel }}
                                </p>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Staff land here too; "You're logged in!" was a placeholder
                     that never got replaced. -->
                <div v-else class="elevated rounded-xl border bg-surface p-6">
                    <p class="font-semibold text-foreground">Welcome back, {{ auth.user?.name }}</p>
                    <p class="mt-1 text-sm text-foreground-secondary">
                        Your day-to-day tools are in the sidebar.
                    </p>
                </div>
            </div>
        </div>
    </component>
</template>
