<script setup>
import { computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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

// Dashboard only ever shows what's still relevant — past bookings live in
// each module's dedicated "my bookings" page, not here.
const upcomingItems = computed(() => {
    const hotel = hotelStore.myBookings
        .filter((b) => b.status !== 'cancelled' && (b.check_out_date ?? '').slice(0, 10) >= today)
        .map((b) => ({
            key: `hotel-${b.id}`,
            type: 'Hotel',
            title: b.room?.hotel?.name,
            detail: `${b.check_in_date?.slice(0, 10)} to ${b.check_out_date?.slice(0, 10)}`,
            date: (b.check_in_date ?? '').slice(0, 10),
            to: { name: 'bookings.confirm', params: { id: b.id } },
        }));

    const ferry = ferryStore.myTickets
        .filter((t) => t.status === 'issued' && (t.schedule?.departure_date ?? '').slice(0, 10) >= today)
        .map((t) => ({
            key: `ferry-${t.id}`,
            type: 'Ferry',
            title: t.schedule?.ferry?.name,
            detail: `${t.schedule?.departure_date?.slice(0, 10)} at ${t.schedule?.departure_time} (seat ${t.seat_number})`,
            date: (t.schedule?.departure_date ?? '').slice(0, 10),
            to: { name: 'ferry.my-tickets' },
        }));

    const themepark = themeParkStore.myBookings
        .filter((b) => b.status === 'confirmed' && (b.slot?.slot_date ?? '').slice(0, 10) >= today)
        .map((b) => ({
            key: `themepark-${b.id}`,
            type: 'Theme Park',
            title: b.slot?.event?.name,
            detail: `${b.slot?.slot_date?.slice(0, 10)} at ${b.slot?.slot_time} (${b.ticket_count} ticket(s))`,
            date: (b.slot?.slot_date ?? '').slice(0, 10),
            to: { name: 'themepark.my-bookings' },
        }));

    return [...hotel, ...ferry, ...themepark].sort((a, b) => a.date.localeCompare(b.date));
});
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
                        <router-link
                            v-for="item in upcomingItems"
                            :key="item.key"
                            :to="item.to"
                            class="flex items-center justify-between rounded-lg bg-white p-4 shadow-sm hover:shadow-md"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">
                                        {{ item.type }}
                                    </span>
                                    <p class="font-semibold text-gray-900">{{ item.title }}</p>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">{{ item.detail }}</p>
                            </div>
                        </router-link>
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
