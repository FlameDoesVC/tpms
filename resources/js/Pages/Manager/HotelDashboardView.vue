<script setup>
import { computed, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useHotelStore } from '@/stores/hotel';

const hotelStore = useHotelStore();
const statusFilter = ref('all');

onMounted(() => {
    hotelStore.fetchHotels();
    hotelStore.fetchMyBookings();
});

const today = new Date().toISOString().slice(0, 10);

const filteredBookings = computed(() => {
    if (statusFilter.value === 'all') return hotelStore.myBookings;
    return hotelStore.myBookings.filter((b) => b.status === statusFilter.value);
});

// Stats reflect only the currently loaded page of bookings, since there is
// no dedicated aggregates endpoint yet.
const totalRooms = computed(() =>
    hotelStore.hotels.reduce((sum, h) => sum + (h.total_rooms || 0), 0)
);
const bookedToday = computed(() =>
    hotelStore.myBookings.filter((b) => b.check_in_date === today && b.status !== 'cancelled').length
);
const occupancyRate = computed(() => {
    if (!totalRooms.value) return 0;
    return Math.round((bookedToday.value / totalRooms.value) * 100);
});

const setStatus = async (booking, status) => {
    if (status === 'confirmed') await hotelStore.confirmBooking(booking.id);
    else await hotelStore.cancelBooking(booking.id);
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Hotel Dashboard
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Total Rooms</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ totalRooms }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Booked Today</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ bookedToday }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Occupancy Rate</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ occupancyRate }}%</p>
                    </div>
                </div>

                <div class="rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b p-4">
                        <h3 class="font-semibold text-gray-900">Bookings</h3>
                        <select v-model="statusFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="all">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div v-if="hotelStore.loading.bookings" class="p-4 text-gray-500">Loading...</div>
                    <table v-else class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="p-4">Guest</th>
                                <th class="p-4">Room</th>
                                <th class="p-4">Dates</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="booking in filteredBookings" :key="booking.id">
                                <td class="p-4">{{ booking.user?.name }}</td>
                                <td class="p-4 capitalize">
                                    {{ booking.room?.type }} - {{ booking.room?.room_number }}
                                </td>
                                <td class="p-4">{{ booking.check_in_date?.slice(0, 10) }} to {{ booking.check_out_date?.slice(0, 10) }}</td>
                                <td class="p-4">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-yellow-100 text-yellow-800': booking.status === 'pending',
                                            'bg-green-100 text-green-800': booking.status === 'confirmed',
                                            'bg-gray-100 text-gray-600': booking.status === 'cancelled',
                                        }"
                                    >
                                        {{ booking.status }}
                                    </span>
                                </td>
                                <td class="p-4 space-x-2">
                                    <button
                                        v-if="booking.status === 'pending'"
                                        @click="setStatus(booking, 'confirmed')"
                                        class="text-sm text-indigo-600 hover:underline"
                                    >
                                        Confirm
                                    </button>
                                    <button
                                        v-if="booking.status !== 'cancelled'"
                                        @click="setStatus(booking, 'cancelled')"
                                        class="text-sm text-red-600 hover:underline"
                                    >
                                        Cancel
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
