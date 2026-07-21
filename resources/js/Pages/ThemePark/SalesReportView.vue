<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, BarElement, CategoryScale, LinearScale } from 'chart.js';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useThemeParkStore } from '@/stores/themepark';

ChartJS.register(Title, Tooltip, BarElement, CategoryScale, LinearScale);

const themeParkStore = useThemeParkStore();
const date = ref(new Date().toISOString().slice(0, 10));

const load = () => themeParkStore.fetchSalesReport(date.value);
onMounted(load);
watch(date, load);

const totalTickets = computed(() =>
    themeParkStore.salesReport.reduce((sum, r) => sum + r.tickets_sold, 0)
);
const totalRevenue = computed(() =>
    themeParkStore.salesReport.reduce((sum, r) => sum + Number(r.revenue), 0).toFixed(2)
);

const chartData = computed(() => ({
    labels: themeParkStore.salesReport.map((r) => r.event_name),
    datasets: [{
        label: 'Tickets Sold',
        backgroundColor: '#6366f1',
        data: themeParkStore.salesReport.map((r) => r.tickets_sold),
    }],
}));

const chartOptions = { responsive: true, plugins: { legend: { display: false } } };
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Theme Park Sales Report
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" v-model="date" class="mt-1 rounded-md border-gray-300 shadow-sm" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Tickets Sold</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ totalTickets }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">Revenue</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">${{ totalRevenue }}</p>
                    </div>
                </div>

                <div v-if="themeParkStore.salesReport.length" class="rounded-lg bg-white p-4 shadow-sm">
                    <Bar :data="chartData" :options="chartOptions" />
                </div>

                <div class="rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="p-4">Event</th>
                                <th class="p-4">Tickets</th>
                                <th class="p-4">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in themeParkStore.salesReport" :key="row.event_id">
                                <td class="p-4">{{ row.event_name }}</td>
                                <td class="p-4">{{ row.tickets_sold }}</td>
                                <td class="p-4">${{ row.revenue }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="themeParkStore.salesReport.length === 0" class="p-4 text-sm text-gray-500">
                        No sales for this date.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
