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
import { useFerryStore } from '@/stores/ferry';

const ferryStore = useFerryStore();
const date = ref(new Date().toISOString().slice(0, 10));
const selectedScheduleId = ref(null);

const schedulesForDate = computed(() => ferryStore.schedules);

const scheduleOptions = computed(() =>
    schedulesForDate.value.map((s) => ({ value: s.id, label: `${s.ferry?.name} - ${s.departure_time}` }))
);

const loadSchedules = () => ferryStore.fetchSchedules(date.value);

onMounted(loadSchedules);
watch(date, loadSchedules);
watch(selectedScheduleId, (id) => {
    if (id) ferryStore.fetchPassengers(id);
});

const exportCsv = () => {
    const rows = [['Ticket Ref', 'Seat', 'Passenger', 'Hotel Ref', 'Status', 'Payment']];
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
    const csv = rows.map((r) => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `passengers-schedule-${selectedScheduleId.value}.csv`;
    link.click();
    URL.revokeObjectURL(url);
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Passenger List" icon="users" />
        </template>

        <div class="max-w-4xl space-y-6">
            <TCard>
                <div class="flex flex-wrap items-end gap-4">
                    <TDatePicker v-model="date" label="Date" />
                    <TSelect v-model="selectedScheduleId" label="Schedule" :options="scheduleOptions" class="w-56" />
                    <TButton variant="secondary" :disabled="!selectedScheduleId" @click="exportCsv">
                        <TIcon name="download" :size="16" />
                        Export CSV
                    </TButton>
                </div>
            </TCard>

            <TCard v-if="selectedScheduleId" icon="users" title="Passengers" :padding="false">
                <div v-if="ferryStore.passengers.length === 0" class="p-4">
                    <TEmptyState
                        title="No passengers"
                        description="No tickets have been issued for this departure yet."
                        icon="ticket"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[rgb(var(--color-border))] text-sm">
                        <thead>
                            <tr class="text-left text-foreground-muted">
                                <th class="p-4">Ticket Ref</th>
                                <th class="p-4">Seat</th>
                                <th class="p-4">Passenger</th>
                                <th class="p-4">Hotel Ref</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Payment</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgb(var(--color-border))] text-foreground-secondary">
                            <tr v-for="ticket in ferryStore.passengers" :key="ticket.id">
                                <td class="p-4 font-mono text-xs text-foreground">{{ ticket.reference_code }}</td>
                                <td class="p-4">{{ ticket.seat_number }}</td>
                                <td class="p-4 text-foreground">{{ ticket.user?.name }}</td>
                                <td class="p-4 font-mono text-xs">{{ ticket.booking?.reference_code }}</td>
                                <td class="p-4 capitalize">{{ ticket.status }}</td>
                                <td class="p-4">
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
