<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, BarElement, CategoryScale, LinearScale } from 'chart.js';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TStat from '@/Components/ui/TStat.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
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
        backgroundColor: 'rgb(13, 110, 110)',
        borderRadius: 4,
        data: themeParkStore.salesReport.map((r) => r.tickets_sold),
    }],
}));

const chartOptions = {
    responsive: true,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'rgb(13, 110, 110)',
            padding: 10,
            cornerRadius: 6,
            displayColors: false,
        },
    },
    scales: {
        x: {
            grid: { display: false },
            border: { color: 'rgba(148, 163, 184, 0.35)' },
            ticks: { color: 'rgba(100, 116, 139, 1)' },
        },
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.2)' },
            border: { display: false },
            ticks: { color: 'rgba(100, 116, 139, 1)', precision: 0 },
        },
    },
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Theme Park Sales Report" icon="report" />
        </template>

        <div class="max-w-4xl space-y-6">
            <TCard>
                <label for="report-date" class="mb-1.5 block text-sm font-medium text-foreground">Date</label>
                <input
                    id="report-date"
                    type="date"
                    v-model="date"
                    class="rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
            </TCard>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <TStat label="Tickets Sold" :value="totalTickets" icon="ticket" tone="primary" />
                <TStat label="Revenue" :value="`$${totalRevenue}`" icon="card" tone="success" />
            </div>

            <TCard v-if="themeParkStore.salesReport.length" icon="capacity" title="Tickets by Event">
                <Bar :data="chartData" :options="chartOptions" />
            </TCard>

            <TCard v-if="themeParkStore.salesReport.length" icon="report" title="Breakdown" :padding="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[rgb(var(--color-border))] text-sm">
                        <thead>
                            <tr class="text-left text-foreground-muted">
                                <th class="p-4">Event</th>
                                <th class="p-4">Tickets</th>
                                <th class="p-4">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgb(var(--color-border))] text-foreground">
                            <tr v-for="row in themeParkStore.salesReport" :key="row.event_id">
                                <td class="p-4">{{ row.event_name }}</td>
                                <td class="p-4">{{ row.tickets_sold }}</td>
                                <td class="p-4">${{ row.revenue }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>

            <TEmptyState
                v-else
                title="No sales for this date"
                description="Pick another date to see ticket sales and revenue."
                icon="ticket"
            />
        </div>
    </StaffLayout>
</template>
