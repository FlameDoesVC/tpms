<script setup>
import { computed, onMounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TStat from '@/Components/ui/TStat.vue';
import TCard from '@/Components/ui/TCard.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import { useHotelStore } from '@/stores/hotel';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { formatDateRange, nightsBetween } from '@/utils/format';

const STATUS_FILTER_OPTIONS = [
    { value: 'all', label: 'All statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const hotelStore = useHotelStore();
const confirm = useConfirm();
const statusFilter = ref('all');
const search = ref('');
const busyId = ref(null);

onMounted(() => {
    hotelStore.fetchHotels();
    hotelStore.fetchMyBookings();
});

const today = new Date().toISOString().slice(0, 10);
const dayOf = (value) => (value ?? '').slice(0, 10);

const filteredBookings = computed(() => {
    let list = hotelStore.myBookings;

    if (statusFilter.value !== 'all') {
        list = list.filter((b) => b.status === statusFilter.value);
    }

    const query = search.value.trim().toLowerCase();
    if (query) {
        list = list.filter((b) =>
            `${b.user?.name ?? ''} ${b.reference_code ?? ''} ${b.room?.room_number ?? ''}`
                .toLowerCase()
                .includes(query)
        );
    }
    return list;
});

// Stats reflect only the currently loaded page of bookings, since there is
// no dedicated aggregates endpoint yet.
const totalRooms = computed(() =>
    hotelStore.hotels.reduce((sum, h) => sum + (h.total_rooms || 0), 0)
);

const liveBookings = computed(() => hotelStore.myBookings.filter((b) => b.status !== 'cancelled'));

const arrivalsToday = computed(() => liveBookings.value.filter((b) => dayOf(b.check_in_date) === today).length);
const departuresToday = computed(() => liveBookings.value.filter((b) => dayOf(b.check_out_date) === today).length);

// Rooms with someone actually in them tonight: checked in on or before today
// and not yet checked out. The old figure counted only guests arriving today
// and called the result "occupancy", which under-reported every stay past its
// first night.
const inHouse = computed(() =>
    liveBookings.value.filter((b) => dayOf(b.check_in_date) <= today && dayOf(b.check_out_date) > today).length
);

const occupancyRate = computed(() => {
    if (!totalRooms.value) return 0;
    return Math.round((inHouse.value / totalRooms.value) * 100);
});

const summary = computed(() => {
    const total = hotelStore.myBookings.length;
    if (!total) return null;
    const shown = filteredBookings.value.length;
    return shown === total ? `${total} booking${total === 1 ? '' : 's'}` : `${shown} of ${total} bookings`;
});

const statusVariant = (status) => ({
    pending: 'warning',
    confirmed: 'success',
    cancelled: 'neutral',
}[status] ?? 'neutral');

const confirmBooking = async (booking) => {
    busyId.value = booking.id;
    try {
        await hotelStore.confirmBooking(booking.id);
        showToast(`${booking.reference_code ?? 'Booking'} confirmed.`, 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not confirm this booking.');
    } finally {
        busyId.value = null;
    }
};

// Cancelling is the one irreversible thing on this screen, and it was a bare
// text link sitting next to Confirm.
const cancelBooking = async (booking) => {
    const ok = await confirm({
        title: `Cancel ${booking.reference_code ?? 'this booking'}?`,
        message: `${booking.user?.name ?? 'The guest'} loses the room for ${formatDateRange(booking.check_in_date, booking.check_out_date)}. This cannot be undone from here.`,
        confirmLabel: 'Cancel booking',
        cancelLabel: 'Keep it',
        danger: true,
    });
    if (!ok) return;

    busyId.value = booking.id;
    try {
        await hotelStore.cancelBooking(booking.id);
        showToast('Booking cancelled.', 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not cancel this booking.');
    } finally {
        busyId.value = null;
    }
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Hotel Dashboard" icon="calendar" />
        </template>

        <div class="space-y-5">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <TStat label="Total rooms" :value="totalRooms" icon="bed" tone="info" />
                <TStat label="Arrivals today" :value="arrivalsToday" icon="calendar" tone="primary" />
                <TStat label="Departures today" :value="departuresToday" icon="logout" tone="warning" />
                <TStat
                    label="In house tonight"
                    :value="`${occupancyRate}%`"
                    :change="`${inHouse} of ${totalRooms} rooms`"
                    icon="capacity"
                    tone="success"
                    :progress="occupancyRate / 100"
                />
            </div>

            <StaffToolbar title="Bookings" :summary="summary">
                <TInput
                    v-model="search"
                    type="search"
                    label="Search"
                    placeholder="Guest, reference or room number"
                    class="min-w-[16rem] flex-1"
                >
                    <template #prefix><TIcon name="search" :size="16" /></template>
                </TInput>
                <div class="w-48">
                    <TSelect v-model="statusFilter" label="Status" :options="STATUS_FILTER_OPTIONS" />
                </div>
            </StaffToolbar>

            <TCard :padding="false">
                <div v-if="hotelStore.loading.bookings" class="p-8 text-center text-sm text-foreground-muted">
                    Loading bookings…
                </div>
                <div v-else-if="filteredBookings.length === 0" class="p-4">
                    <TEmptyState
                        title="No bookings here"
                        :description="hotelStore.myBookings.length
                            ? 'Nothing matches the current search or status filter.'
                            : 'Bookings for your rooms will appear here as they come in.'"
                        icon="calendar"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Room</th>
                                <th>Stay</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in filteredBookings" :key="booking.id">
                                <td>
                                    <p class="font-medium text-foreground">{{ booking.user?.name ?? 'Guest' }}</p>
                                    <p v-if="booking.reference_code" class="mt-0.5 font-mono text-xs text-foreground-muted">
                                        {{ booking.reference_code }}
                                    </p>
                                </td>
                                <td class="capitalize">
                                    {{ booking.room?.type }}
                                    <span class="text-foreground-muted">· {{ booking.room?.room_number }}</span>
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ formatDateRange(booking.check_in_date, booking.check_out_date) }}
                                    <span class="text-xs text-foreground-muted">
                                        · {{ nightsBetween(booking.check_in_date, booking.check_out_date) }}n
                                    </span>
                                </td>
                                <td>
                                    <TBadge :variant="statusVariant(booking.status)" class="capitalize">
                                        {{ booking.status }}
                                    </TBadge>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <TButton
                                            v-if="booking.status === 'pending'"
                                            size="xs"
                                            :loading="busyId === booking.id"
                                            @click="confirmBooking(booking)"
                                        >
                                            Confirm
                                        </TButton>
                                        <TButton
                                            v-if="booking.status !== 'cancelled'"
                                            variant="secondary"
                                            size="xs"
                                            :disabled="busyId === booking.id"
                                            @click="cancelBooking(booking)"
                                        >
                                            Cancel
                                        </TButton>
                                        <span v-if="booking.status === 'cancelled'" class="text-xs text-foreground-muted">
                                            No actions
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>
        </div>
    </StaffLayout>
</template>
