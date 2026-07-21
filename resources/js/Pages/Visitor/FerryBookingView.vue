<script setup>
import { computed, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useFerryStore } from '@/stores/ferry';
import { useHotelStore } from '@/stores/hotel';
import { useAuthStore } from '@/stores/auth';

const ferryStore = useFerryStore();
const hotelStore = useHotelStore();
const auth = useAuthStore();

const date = ref(new Date().toISOString().slice(0, 10));
const purchaseError = ref('');
const purchasedScheduleId = ref(null);

const confirmedBookings = computed(() =>
    hotelStore.myBookings.filter((b) => b.status === 'confirmed')
);
const hasConfirmedBooking = computed(() => confirmedBookings.value.length > 0);

const today = new Date().toISOString().slice(0, 10);
const upcomingTickets = computed(() =>
    ferryStore.myTickets
        .filter((t) => t.status === 'issued' && (t.schedule?.departure_date ?? '').slice(0, 10) >= today)
        .slice(0, 3)
);

const load = () => ferryStore.fetchSchedules(date.value);

onMounted(() => {
    load();
    if (auth.isAuthenticated) {
        hotelStore.fetchMyBookings();
        ferryStore.fetchMyTickets();
    }
});

const fillPercent = (schedule) => {
    const capacity = schedule.ferry?.capacity || 1;
    return Math.round(((capacity - schedule.available_seats) / capacity) * 100);
};

const purchase = async (schedule) => {
    purchaseError.value = '';

    if (!hasConfirmedBooking.value) {
        purchaseError.value = 'confirmed_booking_required';
        return;
    }

    try {
        await ferryStore.purchaseTicket(schedule.id, confirmedBookings.value[0].id);
        purchasedScheduleId.value = schedule.id;
        load();
    } catch (e) {
        purchaseError.value = Object.values(e.response?.data?.errors ?? {}).flat().join(' ')
            || 'Could not purchase ticket.';
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Ferry Schedules
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="auth.isAuthenticated" class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">My Tickets</h3>
                        <router-link :to="{ name: 'ferry.my-tickets' }" class="text-sm text-indigo-600 hover:underline">
                            View all
                        </router-link>
                    </div>

                    <p v-if="upcomingTickets.length === 0" class="mt-2 text-sm text-gray-500">
                        No upcoming tickets.
                    </p>
                    <div v-else class="mt-3 space-y-2">
                        <div
                            v-for="ticket in upcomingTickets"
                            :key="ticket.id"
                            class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 text-sm"
                        >
                            <span>
                                {{ ticket.schedule?.ferry?.name }} -
                                {{ ticket.schedule?.departure_date?.slice(0, 10) }} at {{ ticket.schedule?.departure_time }}
                                (seat {{ ticket.seat_number }})
                            </span>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" v-model="date" @change="load" class="mt-1 rounded-md border-gray-300 shadow-sm" />
                </div>

                <div v-if="purchaseError === 'confirmed_booking_required'" class="rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800">
                    You must have a confirmed hotel booking to purchase a ferry ticket.
                    <router-link :to="{ name: 'hotels.index' }" class="font-medium underline">Browse hotels</router-link>
                </div>
                <div v-else-if="purchaseError" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
                    {{ purchaseError }}
                </div>

                <div v-if="purchasedScheduleId" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">
                    Ticket purchased!
                    <router-link :to="{ name: 'ferry.my-tickets' }" class="font-medium underline">View my tickets</router-link>
                </div>

                <div v-if="ferryStore.loading.schedules" class="text-gray-500">Loading schedules...</div>
                <div v-else-if="ferryStore.schedules.length === 0" class="text-gray-500">
                    No departures scheduled for this date.
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="schedule in ferryStore.schedules"
                        :key="schedule.id"
                        class="rounded-lg bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-gray-900">{{ schedule.ferry?.name }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ schedule.departure_time }} - {{ schedule.arrival_time }}
                                </p>
                            </div>
                            <PrimaryButton
                                :disabled="schedule.available_seats < 1 || purchasedScheduleId === schedule.id"
                                @click="purchase(schedule)"
                            >
                                {{ purchasedScheduleId === schedule.id ? 'Purchased' : 'Purchase Ticket' }}
                            </PrimaryButton>
                        </div>

                        <div class="mt-3">
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                <div
                                    class="h-full rounded-full"
                                    :class="fillPercent(schedule) > 90 ? 'bg-red-500' : 'bg-indigo-500'"
                                    :style="{ width: fillPercent(schedule) + '%' }"
                                />
                            </div>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ schedule.available_seats }} of {{ schedule.ferry?.capacity }} seats available
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
