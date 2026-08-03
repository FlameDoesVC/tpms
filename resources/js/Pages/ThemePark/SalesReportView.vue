<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, BarElement, CategoryScale, LinearScale } from 'chart.js';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TStat from '@/Components/ui/TStat.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TDatePicker from '@/Components/ui/TDatePicker.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import { useThemeParkStore } from '@/stores/themepark';
import { useChartTheme } from '@/composables/useChartTheme';
import { formatDate, formatMoney } from '@/utils/format';

ChartJS.register(Title, Tooltip, BarElement, CategoryScale, LinearScale);

const themeParkStore = useThemeParkStore();
const chartTheme = useChartTheme();
const date = ref(new Date().toISOString().slice(0, 10));

const load = () => themeParkStore.fetchSalesReport(date.value);
onMounted(load);
watch(date, load);

const rows = computed(() => themeParkStore.salesReport);

const totalTickets = computed(() => rows.value.reduce((sum, r) => sum + r.tickets_sold, 0));
const totalRevenue = computed(() => rows.value.reduce((sum, r) => sum + Number(r.revenue), 0));

// Colours were hardcoded to the light theme (a dark teal bar, slate axis
// labels), which on the dark theme left the axes almost invisible and the bar a
// different teal from every other primary element on screen. useChartTheme
// reads them from the same CSS variables everything else uses and re-reads on a
// theme flip; the admin dashboard shares it.
const chartData = computed(() => ({
    labels: rows.value.map((r) => r.event_name),
    datasets: [{
        label: 'Tickets sold',
        backgroundColor: chartTheme.value.primary,
        borderRadius: 4,
        maxBarThickness: 64,
        data: rows.value.map((r) => r.tickets_sold),
    }],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        // One series, so the card title names it and no legend is needed.
        legend: { display: false },
        tooltip: {
            backgroundColor: chartTheme.value.surface,
            titleColor: chartTheme.value.ink,
            bodyColor: chartTheme.value.ink,
            borderColor: chartTheme.value.grid,
            borderWidth: 1,
            padding: 10,
            cornerRadius: 6,
            displayColors: false,
        },
    },
    scales: {
        x: {
            grid: { display: false },
            border: { color: chartTheme.value.grid },
            ticks: { color: chartTheme.value.label },
        },
        y: {
            beginAtZero: true,
            grid: { color: chartTheme.value.grid },
            border: { display: false },
            ticks: { color: chartTheme.value.label, precision: 0 },
        },
    },
}));
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Sales Report" icon="report" />
        </template>

        <div class="space-y-5">
            <StaffToolbar
                title="Ticket sales"
                :summary="rows.length ? `${formatDate(date)} · ${rows.length} event${rows.length === 1 ? '' : 's'}` : formatDate(date)"
            >
                <TDatePicker v-model="date" label="Date" class="w-44" />
            </StaffToolbar>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <TStat label="Tickets sold" :value="totalTickets" icon="ticket" tone="primary" />
                <TStat label="Revenue" :value="formatMoney(totalRevenue)" icon="card" tone="success" />
                <TStat label="Events selling" :value="rows.length" icon="sparkle" tone="info" />
                <TStat
                    label="Average ticket"
                    :value="totalTickets ? formatMoney(totalRevenue / totalTickets) : formatMoney(0)"
                    icon="report"
                    tone="warning"
                />
            </div>

            <TEmptyState
                v-if="rows.length === 0"
                title="No sales for this date"
                description="Pick another date to see ticket sales and revenue."
                icon="ticket"
            />

            <div v-else class="grid gap-5 xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <TCard icon="capacity" title="Tickets by event">
                    <!-- Fixed height with maintainAspectRatio off: in a fluid
                         column an aspect-ratio chart grew taller as the page got
                         wider, which is the opposite of what you want. -->
                    <div class="h-72">
                        <Bar :data="chartData" :options="chartOptions" />
                    </div>
                </TCard>

                <TCard icon="report" title="Breakdown" :padding="false">
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th class="num">Tickets</th>
                                    <th class="num">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in rows" :key="row.event_id">
                                    <td class="font-medium text-foreground">{{ row.event_name }}</td>
                                    <td class="num">{{ row.tickets_sold }}</td>
                                    <td class="num text-foreground">{{ formatMoney(row.revenue) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="border-t bg-surface-sunken">
                                    <td class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-foreground-muted">
                                        Total
                                    </td>
                                    <td class="num px-4 py-3 font-semibold text-foreground">{{ totalTickets }}</td>
                                    <td class="num px-4 py-3 font-semibold text-foreground">{{ formatMoney(totalRevenue) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </TCard>
            </div>
        </div>
    </StaffLayout>
</template>
