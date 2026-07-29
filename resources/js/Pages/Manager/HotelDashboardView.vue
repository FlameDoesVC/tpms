<script setup>
import { computed, onMounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TStat from '@/Components/ui/TStat.vue';
import TCard from '@/Components/ui/TCard.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
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

const statusVariant = (status) => ({
    pending: 'warning',
    confirmed: 'success',
    cancelled: 'neutral',
}[status] ?? 'neutral');

const setStatus = async (booking, status) => {
    if (status === 'confirmed') await hotelStore.confirmBooking(booking.id);
    else await hotelStore.cancelBooking(booking.id);
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Hotel Dashboard" icon="calendar" />
        </template>

        <div class="space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <TStat label="Total Rooms" :value="totalRooms" icon="bed" tone="info" />
                <TStat label="Booked Today" :value="bookedToday" icon="calendar" tone="primary" />
                <TStat
                    label="Occupancy Rate"
                    :value="`${occupancyRate}%`"
                    icon="capacity"
                    tone="success"
                    :progress="occupancyRate / 100"
                />
            </div>

            <TCard icon="inbox" title="Bookings" :padding="false">
                <template #headerAction>
                    <select
                        v-model="statusFilter"
                        class="rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="all">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </template>

                <div v-if="hotelStore.loading.bookings" class="p-4 text-foreground-muted">Loading...</div>
                <div v-else-if="filteredBookings.length === 0" class="p-4">
                    <TEmptyState
                        title="No bookings"
                        description="No bookings match the selected status filter."
                        icon="calendar"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[rgb(var(--color-border))] text-sm">
                        <thead>
                            <tr class="text-left text-foreground-muted">
                                <th class="p-4">Guest</th>
                                <th class="p-4">Room</th>
                                <th class="p-4">Dates</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgb(var(--color-border))] text-foreground-secondary">
                            <tr v-for="booking in filteredBookings" :key="booking.id">
                                <td class="p-4 text-foreground">{{ booking.user?.name }}</td>
                                <td class="p-4 capitalize">
                                    {{ booking.room?.type }} - {{ booking.room?.room_number }}
                                </td>
                                <td class="p-4">{{ booking.check_in_date?.slice(0, 10) }} to {{ booking.check_out_date?.slice(0, 10) }}</td>
                                <td class="p-4">
                                    <TBadge :variant="statusVariant(booking.status)" class="capitalize">
                                        {{ booking.status }}
                                    </TBadge>
                                </td>
                                <td class="p-4 space-x-2">
                                    <button
                                        v-if="booking.status === 'pending'"
                                        @click="setStatus(booking, 'confirmed')"
                                        class="text-sm text-primary hover:underline"
                                    >
                                        Confirm
                                    </button>
                                    <button
                                        v-if="booking.status !== 'cancelled'"
                                        @click="setStatus(booking, 'cancelled')"
                                        class="text-sm text-danger hover:underline"
                                    >
                                        Cancel
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>
        </div>
    </StaffLayout>
</template>
