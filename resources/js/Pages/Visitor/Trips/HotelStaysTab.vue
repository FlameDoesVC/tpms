<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import TBadge from '@/Components/ui/TBadge.vue';
import TButton from '@/Components/ui/TButton.vue';
import TCheckbox from '@/Components/ui/TCheckbox.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TicketQr from '@/Components/Trips/TicketQr.vue';
import { useHotelStore } from '@/stores/hotel';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { formatDateRange, formatMoney, nightsBetween } from '@/utils/format';
import { statusVariant } from '@/utils/status';

const router = useRouter();
const hotelStore = useHotelStore();
const confirm = useConfirm();

const selected = ref([]);
const busyIds = ref([]);

onMounted(() => hotelStore.fetchMyBookings());

// Cancelled bookings sink to the bottom rather than cluttering the top.
const sortedBookings = computed(() =>
    [...hotelStore.myBookings].sort((a, b) => (a.status === 'cancelled') - (b.status === 'cancelled'))
);

// Read per render rather than captured once at setup: a tab left open past
// midnight would otherwise keep yesterday's idea of what's still cancellable.
const todayIso = () => new Date().toISOString().slice(0, 10);
const isUpcoming = (booking) => (booking.check_out_date ?? '').slice(0, 10) >= todayIso();
const isCancellable = (booking) => booking.status !== 'cancelled' && isUpcoming(booking);

const payableSelected = computed(() =>
    selected.value.filter((id) => hotelStore.myBookings.find((b) => b.id === id)?.status === 'pending')
);
const cancellableSelected = computed(() =>
    selected.value.filter((id) => isCancellable(hotelStore.myBookings.find((b) => b.id === id) ?? {}))
);
const selectedTotal = computed(() =>
    hotelStore.myBookings
        .filter((b) => payableSelected.value.includes(b.id))
        .reduce((sum, b) => sum + Number(b.total_price), 0)
);

// The bulk bar only earns its space once there's something to act on - it used
// to sit there permanently reading "0 selected" with both buttons disabled.
const selectableBookings = computed(() =>
    sortedBookings.value.filter((b) => b.status === 'pending' || isCancellable(b))
);
const showBulkBar = computed(() => selectableBookings.value.length > 1);
const allSelected = computed(() =>
    selectableBookings.value.length > 0 && selected.value.length === selectableBookings.value.length
);

const toggleSelectAll = () => {
    selected.value = allSelected.value ? [] : selectableBookings.value.map((b) => b.id);
};

const toggleOne = (id, checked) => {
    selected.value = checked ? [...selected.value, id] : selected.value.filter((x) => x !== id);
};

const cancel = async (booking) => {
    const ok = await confirm({
        title: 'Cancel this stay?',
        message: `${booking.room?.hotel?.name ?? 'This booking'} · ${formatDateRange(booking.check_in_date, booking.check_out_date)}. The room goes back on sale immediately.`,
        confirmLabel: 'Cancel stay',
        cancelLabel: 'Keep it',
        danger: true,
    });
    if (!ok) return;

    busyIds.value = [...busyIds.value, booking.id];
    try {
        await hotelStore.cancelBooking(booking.id);
        showToast('Booking cancelled.', 'success');
    } catch {
        showToast('Could not cancel that booking.', 'error');
    } finally {
        busyIds.value = busyIds.value.filter((id) => id !== booking.id);
    }
};

const paySelected = () => {
    if (payableSelected.value.length === 0) return;
    router.push({ name: 'bookings.confirm', query: { ids: payableSelected.value.join(',') } });
};

const cancelSelected = async () => {
    const ids = [...cancellableSelected.value];
    if (ids.length === 0) return;

    const ok = await confirm({
        title: `Cancel ${ids.length} stay${ids.length === 1 ? '' : 's'}?`,
        message: 'The rooms go back on sale immediately. This cannot be undone.',
        confirmLabel: `Cancel ${ids.length} stay${ids.length === 1 ? '' : 's'}`,
        cancelLabel: 'Keep them',
        danger: true,
    });
    if (!ok) return;

    busyIds.value = [...busyIds.value, ...ids];
    // allSettled, not all: one rejection used to abandon the rest half-done
    // with no indication of which succeeded.
    const results = await Promise.allSettled(ids.map((id) => hotelStore.cancelBooking(id)));
    busyIds.value = busyIds.value.filter((id) => !ids.includes(id));

    const failed = results.filter((r) => r.status === 'rejected').length;
    if (failed === 0) {
        showToast(`${ids.length} booking${ids.length === 1 ? '' : 's'} cancelled.`, 'success');
    } else {
        showToast(`${failed} of ${ids.length} could not be cancelled.`, 'error');
    }

    selected.value = selected.value.filter((id) => !ids.includes(id));
};
</script>

<template>
    <div class="space-y-4">
        <p v-if="hotelStore.loading.bookings" class="text-foreground-muted">Loading stays…</p>

        <div
            v-else-if="hotelStore.error.bookings"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-danger bg-danger-soft p-4 text-sm text-danger"
        >
            <span>{{ hotelStore.error.bookings }}</span>
            <TButton size="sm" variant="secondary" @click="hotelStore.fetchMyBookings()">Try again</TButton>
        </div>

        <TEmptyState
            v-else-if="hotelStore.myBookings.length === 0"
            title="No stays booked yet"
            description="Once you book a room, it will show up here with your confirmation code."
            icon="hotel"
        >
            <template #action>
                <router-link :to="{ name: 'hotels.index' }">
                    <TButton size="sm">Browse hotels</TButton>
                </router-link>
            </template>
        </TEmptyState>

        <template v-else>
            <div
                v-if="showBulkBar"
                class="elevated flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-surface p-4"
            >
                <TCheckbox
                    :model-value="allSelected"
                    :label="selected.length ? `${selected.length} selected` : 'Select all'"
                    @update:model-value="toggleSelectAll"
                />
                <div class="flex items-center gap-2">
                    <span v-if="payableSelected.length" class="text-sm text-foreground-secondary">
                        {{ formatMoney(selectedTotal) }} due
                    </span>
                    <TButton size="sm" :disabled="payableSelected.length === 0" @click="paySelected">
                        Pay ({{ payableSelected.length }})
                    </TButton>
                    <TButton
                        size="sm"
                        variant="danger"
                        :disabled="cancellableSelected.length === 0"
                        @click="cancelSelected"
                    >
                        Cancel ({{ cancellableSelected.length }})
                    </TButton>
                </div>
            </div>

            <div
                v-for="booking in sortedBookings"
                :key="booking.id"
                class="elevated flex flex-wrap items-center gap-4 rounded-xl border bg-surface p-4 transition-opacity"
                :class="{ 'opacity-60': booking.status === 'cancelled' || busyIds.includes(booking.id) }"
            >
                <TCheckbox
                    v-if="showBulkBar && (booking.status === 'pending' || isCancellable(booking))"
                    :model-value="selected.includes(booking.id)"
                    @update:model-value="(v) => toggleOne(booking.id, v)"
                />

                <TicketQr
                    v-if="booking.status === 'confirmed'"
                    :code="booking.reference_code"
                    size="sm"
                    :show-code="false"
                />

                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-foreground">{{ booking.room?.hotel?.name }}</p>
                    <p class="text-sm capitalize text-foreground-secondary">
                        {{ booking.room?.type }}
                        <span v-if="booking.party_guests_count" class="text-foreground-muted">
                            · {{ booking.party_guests_count }} guest{{ booking.party_guests_count === 1 ? '' : 's' }}
                        </span>
                    </p>
                    <p class="mt-0.5 text-sm text-foreground-muted">
                        {{ formatDateRange(booking.check_in_date, booking.check_out_date) }}
                        · {{ nightsBetween(booking.check_in_date, booking.check_out_date) }}
                        night{{ nightsBetween(booking.check_in_date, booking.check_out_date) === 1 ? '' : 's' }}
                    </p>
                    <p class="mt-0.5 font-mono text-xs text-foreground-muted">{{ booking.reference_code }}</p>
                </div>

                <div class="flex shrink-0 flex-col items-end gap-2">
                    <TBadge :variant="statusVariant(booking.status)" dot>{{ booking.status }}</TBadge>
                    <span class="text-sm font-semibold text-foreground">{{ formatMoney(booking.total_price) }}</span>
                    <div class="flex items-center gap-2">
                        <router-link
                            v-if="booking.status === 'pending'"
                            :to="{ name: 'bookings.confirm', query: { ids: String(booking.id) } }"
                        >
                            <TButton size="xs">Complete payment</TButton>
                        </router-link>
                        <TButton
                            v-if="isCancellable(booking)"
                            size="xs"
                            variant="ghost"
                            :disabled="busyIds.includes(booking.id)"
                            @click="cancel(booking)"
                        >
                            <TIcon name="x" :size="13" />
                            Cancel
                        </TButton>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
