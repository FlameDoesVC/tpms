<script setup>
import { onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useHotelStore } from '@/stores/hotel';

const hotelStore = useHotelStore();

onMounted(() => hotelStore.fetchMyBookings());

const today = new Date().toISOString().slice(0, 10);
const isUpcoming = (booking) => (booking.check_out_date ?? '').slice(0, 10) >= today;

const cancel = (booking) => {
    if (confirm('Cancel this booking?')) {
        hotelStore.cancelBooking(booking.id);
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                My Hotel Bookings
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="hotelStore.loading.bookings" class="text-gray-500">Loading bookings...</div>
                <div v-else-if="hotelStore.myBookings.length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500">
                    You have no hotel bookings yet.
                </div>

                <div
                    v-for="booking in hotelStore.myBookings"
                    :key="booking.id"
                    class="flex items-center justify-between rounded-lg bg-white p-4 shadow-sm"
                >
                    <div>
                        <p class="font-semibold text-gray-900">{{ booking.room?.hotel?.name }}</p>
                        <p class="text-sm text-gray-500">
                            {{ booking.check_in_date?.slice(0, 10) }} to {{ booking.check_out_date?.slice(0, 10) }}
                            - ${{ booking.total_price }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
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
                        <router-link
                            v-if="booking.status === 'pending'"
                            :to="{ name: 'bookings.confirm', params: { id: booking.id } }"
                            class="text-sm text-indigo-600 hover:underline"
                        >
                            Complete Payment
                        </router-link>
                        <button
                            v-if="booking.status !== 'cancelled' && isUpcoming(booking)"
                            @click="cancel(booking)"
                            class="text-sm text-red-600 hover:underline"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
