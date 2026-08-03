<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TButton from '@/Components/ui/TButton.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import { useFerryStore } from '@/stores/ferry';
import { formatDate, formatTime } from '@/utils/format';

const ferryStore = useFerryStore();
const date = ref(new Date().toISOString().slice(0, 10));
const selectedScheduleId = ref(null);

const scheduleOptions = computed(() =>
    ferryStore.schedules.map((s) => ({
        value: s.id,
        label: `${formatTime(s.departure_time)} · ${s.ferry?.name ?? 'Ferry'}`,
    }))
);

const selectedSchedule = computed(() =>
    ferryStore.schedules.find((s) => s.id === selectedScheduleId.value) ?? null
);

const loadSchedules = () => ferryStore.fetchSchedules(date.value);

onMounted(loadSchedules);

// Changing the date reloaded the sailings but left the old selection in place,
// so the manifest on screen still belonged to yesterday's departure while the
// picker showed today's. Clear it, then pick for them when there's no choice
// to make.
watch(date, async () => {
    selectedScheduleId.value = null;
    ferryStore.passengers = [];
    await loadSchedules();
    if (ferryStore.schedules.length === 1) {
        selectedScheduleId.value = ferryStore.schedules[0].id;
    }
});

watch(selectedScheduleId, (id) => {
    if (id) ferryStore.fetchPassengers(id);
});

const STATUS_VARIANT = {
    issued: 'info',
    used: 'success',
    cancelled: 'neutral',
};

const cashDue = computed(() => ferryStore.passengers.filter((t) => t.payment_method === 'cash').length);

const summary = computed(() => {
    if (!selectedScheduleId.value) return null;
    const total = ferryStore.passengers.length;
    if (!total) return 'No tickets issued';
    // The number that decides how much float the crew needs at the gangway.
    return `${total} passenger${total === 1 ? '' : 's'} · ${cashDue.value} paying cash on board`;
});

// Every field quoted, inner quotes doubled. Without this a passenger called
// "Smith, John" silently shifted every column after it one to the right, and a
// manifest that looks fine on screen arrives at the dock corrupted.
const csvCell = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;

const exportCsv = () => {
    const rows = [['Ticket ref', 'Seat', 'Passenger', 'Hotel ref', 'Status', 'Payment']];
    for (const ticket of ferryStore.passengers) {
        rows.push([
            ticket.reference_code,
            ticket.seat_number,
            ticket.user?.name,
            ticket.booking?.reference_code,
            ticket.status,
            ticket.payment_method === 'cash' ? 'Cash due' : 'Paid online',
        ]);
    }

    // Leading BOM so Excel reads it as UTF-8 rather than mangling accented names.
    const csv = '﻿' + rows.map((r) => r.map(csvCell).join(',')).join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = url;
    const ferryName = (selectedSchedule.value?.ferry?.name ?? 'ferry').replace(/[^a-z0-9]+/gi, '-').toLowerCase();
    link.download = `passengers-${ferryName}-${date.value}.csv`;
    // Appended before clicking: a detached anchor is ignored by some browsers.
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Passenger List" icon="users" />
        </template>

        <div class="space-y-5">
            <StaffToolbar title="Manifest" :summary="summary">
                <template #actions>
                    <TButton
                        variant="secondary"
                        :disabled="!selectedScheduleId || ferryStore.passengers.length === 0"
                        @click="exportCsv"
                    >
                        <TIcon name="download" :size="16" />
                        Export CSV
                    </TButton>
                </template>

                <TDatePicker v-model="date" label="Date" class="w-44" />
                <div class="w-64">
                    <TSelect
                        v-model="selectedScheduleId"
                        label="Departure"
                        :options="scheduleOptions"
                        placeholder="Pick a sailing"
                    />
                </div>
                <p v-if="selectedSchedule" class="pb-2 text-xs text-foreground-muted">
                    {{ formatDate(date) }} ·
                    {{ formatTime(selectedSchedule.departure_time) }} – {{ formatTime(selectedSchedule.arrival_time) }}
                    · {{ selectedSchedule.available_seats }} of {{ selectedSchedule.ferry?.capacity }} seats free
                </p>
            </StaffToolbar>

            <!-- Nothing selected is a normal starting state, not an error, so it
                 gets a prompt instead of an empty page. -->
            <TCard v-if="!selectedScheduleId" :padding="false">
                <div class="p-4">
                    <TEmptyState
                        title="Pick a departure"
                        :description="ferryStore.schedules.length
                            ? 'Choose a sailing above to see who is booked on it.'
                            : 'No sailings are scheduled for this date.'"
                        icon="ferry"
                    />
                </div>
            </TCard>

            <TCard v-else :padding="false">
                <div v-if="ferryStore.loading.passengers" class="p-8 text-center text-sm text-foreground-muted">
                    Loading manifest…
                </div>
                <div v-else-if="ferryStore.passengers.length === 0" class="p-4">
                    <TEmptyState
                        title="No passengers yet"
                        description="No tickets have been issued for this departure."
                        icon="ticket"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ticket ref</th>
                                <th class="num">Seat</th>
                                <th>Passenger</th>
                                <th>Hotel ref</th>
                                <th>Status</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ticket in ferryStore.passengers" :key="ticket.id">
                                <td class="font-mono text-xs text-foreground">{{ ticket.reference_code }}</td>
                                <td class="num font-semibold text-foreground">{{ ticket.seat_number }}</td>
                                <td class="text-foreground">{{ ticket.user?.name ?? 'Guest' }}</td>
                                <td class="font-mono text-xs">{{ ticket.booking?.reference_code ?? '—' }}</td>
                                <td>
                                    <TBadge :variant="STATUS_VARIANT[ticket.status] ?? 'neutral'" class="capitalize">
                                        {{ ticket.status }}
                                    </TBadge>
                                </td>
                                <td>
                                    <TBadge :variant="ticket.payment_method === 'cash' ? 'warning' : 'success'">
                                        {{ ticket.payment_method === 'cash' ? 'Cash due' : 'Paid online' }}
                                    </TBadge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>
        </div>
    </StaffLayout>
</template>
