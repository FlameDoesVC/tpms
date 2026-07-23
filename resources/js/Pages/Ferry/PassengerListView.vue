<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useFerryStore } from '@/stores/ferry';

const ferryStore = useFerryStore();
const date = ref(new Date().toISOString().slice(0, 10));
const selectedScheduleId = ref(null);

const schedulesForDate = computed(() => ferryStore.schedules);

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
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Passenger List
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end gap-4 rounded-lg bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Date</label>
                        <input type="date" v-model="date" class="mt-1 rounded-md border-gray-300 shadow-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Schedule</label>
                        <select v-model="selectedScheduleId" class="mt-1 rounded-md border-gray-300 shadow-sm">
                            <option v-for="schedule in schedulesForDate" :key="schedule.id" :value="schedule.id">
                                {{ schedule.ferry?.name }} - {{ schedule.departure_time }}
                            </option>
                        </select>
                    </div>
                    <button
                        :disabled="!selectedScheduleId"
                        @click="exportCsv"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                    >
                        Export CSV
                    </button>
                </div>

                <div v-if="selectedScheduleId" class="rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="p-4">Ticket Ref</th>
                                <th class="p-4">Seat</th>
                                <th class="p-4">Passenger</th>
                                <th class="p-4">Hotel Ref</th>
                                <th class="p-4">Status</th>
                                <th class="p-4">Payment</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="ticket in ferryStore.passengers" :key="ticket.id">
                                <td class="p-4 font-mono text-xs">{{ ticket.reference_code }}</td>
                                <td class="p-4">{{ ticket.seat_number }}</td>
                                <td class="p-4">{{ ticket.user?.name }}</td>
                                <td class="p-4 font-mono text-xs">{{ ticket.booking?.reference_code }}</td>
                                <td class="p-4 capitalize">{{ ticket.status }}</td>
                                <td class="p-4">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="ticket.payment_method === 'cash' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'"
                                    >
                                        {{ ticket.payment_method === 'cash' ? 'Cash due' : 'Paid online' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
