<script setup>
import { computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useThemeParkStore } from '@/stores/themepark';

const themeParkStore = useThemeParkStore();

onMounted(() => themeParkStore.fetchMyBookings());

// Cancelled bookings sink to the bottom rather than cluttering the top of the list.
const sortedBookings = computed(() =>
    [...themeParkStore.myBookings].sort((a, b) => (a.status === 'cancelled') - (b.status === 'cancelled'))
);

const today = new Date().toISOString().slice(0, 10);

const isUpcoming = (booking) => (booking.slot?.slot_date ?? '') >= today;

const cancel = (booking) => {
    if (confirm('Cancel this booking?')) {
        themeParkStore.cancelBooking(booking.id);
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                My Theme Park Bookings
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="themeParkStore.loading.bookings" class="text-gray-500">Loading bookings...</div>
                <div v-else-if="themeParkStore.myBookings.length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500">
                    You have no theme park bookings yet.
                </div>

                <div
                    v-for="booking in sortedBookings"
                    :key="booking.id"
                    class="flex items-center justify-between rounded-lg bg-white p-4 shadow-sm"
                    :class="{ 'opacity-50 grayscale': booking.status === 'cancelled' }"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-gray-900">{{ booking.slot?.event?.name }}</p>
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium capitalize text-indigo-800">
                                {{ booking.slot?.event?.type?.replace('_', ' ') }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500">
                            {{ booking.slot?.slot_date?.slice(0, 10) }} at {{ booking.slot?.slot_time }} - {{ booking.ticket_count }} ticket(s)
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="{
                                'bg-green-100 text-green-800': booking.status === 'confirmed',
                                'bg-gray-100 text-gray-600': booking.status === 'cancelled',
                                'bg-blue-100 text-blue-800': booking.status === 'used',
                            }"
                        >
                            {{ booking.status }}
                        </span>
                        <button
                            v-if="booking.status === 'confirmed' && isUpcoming(booking)"
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
