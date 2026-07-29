<script setup>
import { computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
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
            <TPageHeader title="My Theme Park Bookings" />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="themeParkStore.loading.bookings" class="text-foreground-muted">Loading bookings...</div>
                <TEmptyState
                    v-else-if="themeParkStore.myBookings.length === 0"
                    title="No theme park bookings yet"
                    description="Rides, shows and beach events you book will appear here."
                    icon="ticket"
                />

                <div
                    v-for="booking in sortedBookings"
                    :key="booking.id"
                    class="flex items-center justify-between rounded-xl border bg-surface p-4"
                    :class="{ 'opacity-50 grayscale': booking.status === 'cancelled' }"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-foreground">{{ booking.slot?.event?.name }}</p>
                            <TBadge variant="primary" class="capitalize">
                                {{ booking.slot?.event?.type?.replace('_', ' ') }}
                            </TBadge>
                        </div>
                        <p class="text-sm text-foreground-muted">
                            {{ booking.slot?.slot_date?.slice(0, 10) }} at {{ booking.slot?.slot_time }} - {{ booking.ticket_count }} ticket(s)
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <TBadge
                            :variant="booking.status === 'confirmed' ? 'success' : booking.status === 'used' ? 'info' : 'neutral'"
                        >
                            {{ booking.status }}
                        </TBadge>
                        <button
                            v-if="booking.status === 'confirmed' && isUpcoming(booking)"
                            @click="cancel(booking)"
                            class="text-sm text-danger hover:underline"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
