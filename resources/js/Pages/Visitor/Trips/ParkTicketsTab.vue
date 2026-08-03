<script setup>
import { computed, onMounted, ref } from 'vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TButton from '@/Components/ui/TButton.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TicketQr from '@/Components/Trips/TicketQr.vue';
import { useThemeParkStore } from '@/stores/themepark';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { formatDate, formatTime, todayIso } from '@/utils/format';
import { statusVariant } from '@/utils/status';

const themeParkStore = useThemeParkStore();
const confirm = useConfirm();
const busyIds = ref([]);

onMounted(() => themeParkStore.fetchMyBookings());

// Cancelled bookings sink to the bottom rather than cluttering the top.
const sortedBookings = computed(() =>
    [...themeParkStore.myBookings].sort((a, b) => (a.status === 'cancelled') - (b.status === 'cancelled'))
);

// Sliced before comparing: the raw value can be a full datetime, and read per
// render so a long-open tab doesn't keep yesterday's cutoff.
const isUpcoming = (booking) =>
    (booking.slot?.slot_date ?? '').slice(0, 10) >= todayIso();

const cancel = async (booking) => {
    const ok = await confirm({
        title: 'Cancel this booking?',
        message: `${booking.slot?.event?.name ?? 'This event'} · ${formatDate(booking.slot?.slot_date)} at ${formatTime(booking.slot?.slot_time)}. The tickets go back on sale immediately.`,
        confirmLabel: 'Cancel booking',
        cancelLabel: 'Keep it',
        danger: true,
    });
    if (!ok) return;

    busyIds.value = [...busyIds.value, booking.id];
    try {
        await themeParkStore.cancelBooking(booking.id);
        showToast('Booking cancelled.', 'success');
    } catch {
        showToast('Could not cancel that booking.', 'error');
    } finally {
        busyIds.value = busyIds.value.filter((id) => id !== booking.id);
    }
};
</script>

<template>
    <div class="space-y-4">
        <p v-if="themeParkStore.loading.bookings" class="text-foreground-muted">Loading bookings…</p>

        <div
            v-else-if="themeParkStore.error.bookings"
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-danger bg-danger-soft p-4 text-sm text-danger"
        >
            <span>{{ themeParkStore.error.bookings }}</span>
            <TButton size="sm" variant="secondary" @click="themeParkStore.fetchMyBookings()">Try again</TButton>
        </div>

        <TEmptyState
            v-else-if="themeParkStore.myBookings.length === 0"
            title="No park tickets yet"
            description="Rides, shows and beach events you book will appear here with a code for the gate."
            icon="ticket"
        >
            <template #action>
                <router-link :to="{ name: 'themepark.home' }">
                    <TButton size="sm">Browse events</TButton>
                </router-link>
            </template>
        </TEmptyState>

        <div
            v-for="booking in sortedBookings"
            :key="booking.id"
            class="elevated flex flex-wrap items-center gap-4 rounded-xl border bg-surface p-4 transition-opacity"
            :class="{ 'opacity-60': booking.status === 'cancelled' || busyIds.includes(booking.id) }"
        >
            <!-- The gate scanner reads this code, so a park booking needs one
                 on screen just as much as a ferry ticket does. -->
            <TicketQr
                v-if="booking.status === 'confirmed'"
                :code="booking.reference_code"
                size="sm"
                :show-code="false"
            />

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold text-foreground">{{ booking.slot?.event?.name }}</p>
                    <TBadge variant="neutral" size="sm" class="capitalize">
                        {{ booking.slot?.event?.type?.replace('_', ' ') }}
                    </TBadge>
                </div>
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-foreground-secondary">
                    <span class="inline-flex items-center gap-1">
                        <TIcon name="calendar" :size="13" />
                        {{ formatDate(booking.slot?.slot_date) }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <TIcon name="clock" :size="13" />
                        {{ formatTime(booking.slot?.slot_time) }}
                    </span>
                    <span v-if="booking.slot?.event?.location" class="inline-flex items-center gap-1">
                        <TIcon name="pin" :size="13" />
                        {{ booking.slot.event.location }}
                    </span>
                </p>
                <p class="mt-0.5 font-mono text-xs text-foreground-muted">{{ booking.reference_code }}</p>
            </div>

            <div class="flex shrink-0 flex-col items-end gap-2">
                <TBadge :variant="statusVariant(booking.status)" dot>{{ booking.status }}</TBadge>
                <span class="text-sm text-foreground-secondary">
                    {{ booking.ticket_count }} ticket{{ booking.ticket_count === 1 ? '' : 's' }}
                </span>
                <TButton
                    v-if="booking.status === 'confirmed' && isUpcoming(booking)"
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
