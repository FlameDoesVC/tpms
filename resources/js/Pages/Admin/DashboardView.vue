<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { Line, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS, Tooltip, Legend, LineElement, PointElement,
    BarElement, CategoryScale, LinearScale,
} from 'chart.js';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TStat from '@/Components/ui/TStat.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { useChartTheme, SERIES_COLORS } from '@/composables/useChartTheme';
import { formatDate, formatMoney } from '@/utils/format';

ChartJS.register(Tooltip, Legend, LineElement, PointElement, BarElement, CategoryScale, LinearScale);

const stats = ref(null);
const loading = ref(true);
const error = ref(null);
const chartTheme = useChartTheme();

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/admin/stats');
        stats.value = data;
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to load stats.';
    } finally {
        loading.value = false;
    }
});

const ROLE_VARIANT = {
    visitor: 'neutral',
    hotel_manager: 'info',
    ferry_operator: 'warning',
    themepark_staff: 'success',
    admin: 'danger',
};

const ROLE_LABELS = {
    visitor: 'Visitor',
    hotel_manager: 'Hotel Manager',
    ferry_operator: 'Ferry Operator',
    themepark_staff: 'Theme Park Staff',
    admin: 'Admin',
};

const fmt = (n) => Number(n ?? 0).toLocaleString();

const daily = computed(() => stats.value?.daily ?? []);

const totalRevenue = computed(() =>
    Number(stats.value?.hotel_bookings?.revenue ?? 0) + Number(stats.value?.park_bookings?.revenue ?? 0)
);

const bookingsLast30 = computed(() =>
    daily.value.reduce((sum, d) => sum + d.hotel + d.ferry + d.park, 0)
);
const revenueLast30 = computed(() => daily.value.reduce((sum, d) => sum + Number(d.revenue), 0));

// — Charts ------------------------------------------------------------------

// Short labels; the tooltip carries the full date.
const labels = computed(() => daily.value.map((d) => formatDate(d.date, { weekday: false })));

const SERIES = [
    { key: 'hotel', label: 'Hotel bookings', color: SERIES_COLORS.hotel },
    { key: 'ferry', label: 'Ferry tickets', color: SERIES_COLORS.ferry },
    { key: 'park', label: 'Park bookings', color: SERIES_COLORS.park },
];

const activityData = computed(() => ({
    labels: labels.value,
    datasets: SERIES.map((s) => ({
        label: s.label,
        data: daily.value.map((d) => d[s.key]),
        borderColor: s.color,
        backgroundColor: s.color,
        // 2px lines; no dot per point, an 8px one under the cursor.
        borderWidth: 2,
        pointRadius: 0,
        pointHoverRadius: 4,
        pointHoverBorderWidth: 2,
        // Straight segments: these are daily counts, and a spline invents
        // values between them that were never measured.
        tension: 0,
    })),
}));

// Shared across both charts. `mode: 'index'` is what makes the hover read as a
// crosshair - one tooltip listing every series at that day, rather than three
// separate hit targets the size of a line.
const baseOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        // Legend is rendered in HTML below so it can wear text tokens instead
        // of the series colour.
        legend: { display: false },
        tooltip: {
            backgroundColor: chartTheme.value.surface,
            titleColor: chartTheme.value.ink,
            bodyColor: chartTheme.value.ink,
            borderColor: chartTheme.value.grid,
            borderWidth: 1,
            padding: 10,
            cornerRadius: 6,
            usePointStyle: true,
        },
    },
    scales: {
        x: {
            grid: { display: false },
            border: { color: chartTheme.value.grid },
            ticks: {
                color: chartTheme.value.label,
                maxRotation: 0,
                autoSkipPadding: 24,
            },
        },
        y: {
            beginAtZero: true,
            grid: { color: chartTheme.value.grid },
            border: { display: false },
            ticks: { color: chartTheme.value.label, precision: 0, maxTicksLimit: 5 },
        },
    },
}));

const activityOptions = computed(() => baseOptions.value);

const revenueData = computed(() => ({
    labels: labels.value,
    datasets: [{
        label: 'Revenue',
        data: daily.value.map((d) => Number(d.revenue)),
        backgroundColor: chartTheme.value.primary,
        // Rounded data-end, anchored to the baseline.
        borderRadius: 4,
        maxBarThickness: 18,
    }],
}));

const revenueOptions = computed(() => ({
    ...baseOptions.value,
    plugins: {
        ...baseOptions.value.plugins,
        tooltip: {
            ...baseOptions.value.plugins.tooltip,
            callbacks: { label: (ctx) => formatMoney(ctx.parsed.y) },
        },
    },
    scales: {
        ...baseOptions.value.scales,
        y: {
            ...baseOptions.value.scales.y,
            ticks: {
                ...baseOptions.value.scales.y.ticks,
                callback: (value) => formatMoney(value).replace(/\.00$/, ''),
            },
        },
    },
}));

// — Panels ------------------------------------------------------------------

const roleRows = computed(() => {
    const rows = Object.entries(stats.value?.users?.by_role ?? {}).map(([role, count]) => ({
        key: role,
        label: ROLE_LABELS[role] ?? role,
        variant: ROLE_VARIANT[role] ?? 'neutral',
        count: Number(count ?? 0),
    }));
    if (stats.value?.users?.guests) {
        rows.push({ key: 'guest', label: 'Guest checkout', variant: 'neutral', count: Number(stats.value.users.guests) });
    }
    return rows.sort((a, b) => b.count - a.count);
});

// Things an admin may actually have to act on, each pointing at the screen
// where the acting happens. Status colours carry an icon and a label, never
// colour alone.
const attention = computed(() => {
    if (!stats.value) return [];
    const items = [];
    const pending = Number(stats.value.hotel_bookings.pending ?? 0);
    const cancelled =
        Number(stats.value.hotel_bookings.cancelled ?? 0) +
        Number(stats.value.ferry_tickets.cancelled ?? 0) +
        Number(stats.value.park_bookings.cancelled ?? 0);
    const totalAll =
        Number(stats.value.hotel_bookings.total ?? 0) +
        Number(stats.value.ferry_tickets.total ?? 0) +
        Number(stats.value.park_bookings.total ?? 0);

    if (pending > 0) {
        items.push({
            key: 'pending',
            tone: 'warning',
            icon: 'alert',
            label: `${pending} hotel booking${pending === 1 ? '' : 's'} awaiting confirmation`,
            to: { name: 'manager.hotel-dashboard' },
            action: 'Review',
        });
    }

    const rate = totalAll ? Math.round((cancelled / totalAll) * 100) : 0;
    if (rate >= 20) {
        items.push({
            key: 'cancellations',
            tone: 'danger',
            icon: 'alert',
            label: `${rate}% of all bookings are cancelled (${fmt(cancelled)} of ${fmt(totalAll)})`,
            to: { name: 'manager.hotel-dashboard' },
            action: 'Look into it',
        });
    }

    return items;
});

const TONE_CLASS = {
    warning: 'border-warning/40 bg-warning-soft text-warning',
    danger: 'border-danger/40 bg-danger-soft text-danger',
};

// Wayfinding, so icons earn their place here in a way they don't beside a stat
// that already spells out what it counts.
const quickLinks = [
    { label: 'Users', hint: 'Accounts and roles', icon: 'users', to: { name: 'admin.users' } },
    { label: 'Promotions', hint: 'Offers on the site', icon: 'sparkle', to: { name: 'promotions' } },
    { label: 'Island map', hint: 'Pins and locations', icon: 'map', to: { name: 'admin.map' } },
    { label: 'Hotel bookings', hint: 'Confirm and cancel', icon: 'bed', to: { name: 'manager.hotel-dashboard' } },
    { label: 'Ferry schedules', hint: 'Sailings and patterns', icon: 'ferry', to: { name: 'ferry.schedule-management' } },
    { label: 'Park events', hint: 'Rides, shows, slots', icon: 'ticket', to: { name: 'themepark.event-management' } },
];

// Status split per module, drawn as one thin part-to-whole bar each.
const moduleRows = computed(() => {
    if (!stats.value) return [];
    const build = (title, total, parts) => ({
        title,
        total: Number(total ?? 0),
        parts: parts
            .map((p) => ({ ...p, value: Number(p.value ?? 0) }))
            .filter((p) => p.value > 0),
    });
    return [
        build('Hotel', stats.value.hotel_bookings.total, [
            { label: 'Confirmed', value: stats.value.hotel_bookings.confirmed, class: 'bg-success' },
            { label: 'Pending', value: stats.value.hotel_bookings.pending, class: 'bg-warning' },
            { label: 'Cancelled', value: stats.value.hotel_bookings.cancelled, class: 'bg-foreground-muted/40' },
        ]),
        build('Ferry', stats.value.ferry_tickets.total, [
            { label: 'Used', value: stats.value.ferry_tickets.used, class: 'bg-success' },
            { label: 'Issued', value: stats.value.ferry_tickets.issued, class: 'bg-info' },
            { label: 'Cancelled', value: stats.value.ferry_tickets.cancelled, class: 'bg-foreground-muted/40' },
        ]),
        build('Theme park', stats.value.park_bookings.total, [
            { label: 'Used', value: stats.value.park_bookings.used, class: 'bg-success' },
            { label: 'Confirmed', value: stats.value.park_bookings.confirmed, class: 'bg-info' },
            { label: 'Cancelled', value: stats.value.park_bookings.cancelled, class: 'bg-foreground-muted/40' },
        ]),
    ];
});
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader title="Admin Overview" icon="dashboard" compact />
        </template>

        <!-- The shortcuts need no data, so they render immediately rather than
             sitting behind a skeleton — and the skeleton below mirrors the real
             order so nothing jumps when the numbers land. -->
        <div v-if="loading" class="space-y-5">
            <nav aria-label="Admin shortcuts" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                <router-link
                    v-for="link in quickLinks"
                    :key="link.label"
                    :to="link.to"
                    class="group elevated flex items-center gap-3 rounded-xl border bg-surface p-3 transition-colors hover:border-strong hover:bg-surface-hover"
                >
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded bg-primary-soft text-primary">
                        <TIcon :name="link.icon" :size="17" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-foreground group-hover:text-primary">
                            {{ link.label }}
                        </span>
                        <span class="block truncate text-xs text-foreground-muted">{{ link.hint }}</span>
                    </span>
                </router-link>
            </nav>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
                <div v-for="n in 5" :key="n" class="h-28 animate-pulse rounded-xl border bg-surface-hover" />
            </div>
            <div class="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="h-80 animate-pulse rounded-xl border bg-surface-hover" />
                <div class="h-80 animate-pulse rounded-xl border bg-surface-hover" />
            </div>
        </div>

        <div v-else-if="error" class="rounded-xl border border-danger/40 bg-danger-soft px-4 py-3 text-sm text-danger">
            {{ error }}
        </div>

        <div v-else class="space-y-5">
            <!-- Launcher first: most visits to an overview are on the way
                 somewhere else. Deliberately lighter than the cards below it —
                 a plain grid rather than a titled card — so that being first on
                 the page doesn't make it the loudest thing on it. -->
            <nav aria-label="Admin shortcuts" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
                <router-link
                    v-for="link in quickLinks"
                    :key="link.label"
                    :to="link.to"
                    class="group elevated flex items-center gap-3 rounded-xl border bg-surface p-3 transition-colors hover:border-strong hover:bg-surface-hover"
                >
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded bg-primary-soft text-primary">
                        <TIcon :name="link.icon" :size="17" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-foreground group-hover:text-primary">
                            {{ link.label }}
                        </span>
                        <span class="block truncate text-xs text-foreground-muted">{{ link.hint }}</span>
                    </span>
                </router-link>
            </nav>

            <!-- Headline figures. No icons: the label already names what is
                 being counted, so a glyph beside it is decoration. -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
                <TStat label="Total users" :value="fmt(stats.users.total)" />
                <TStat label="Hotel bookings" :value="fmt(stats.hotel_bookings.total)" />
                <TStat label="Ferry tickets" :value="fmt(stats.ferry_tickets.total)" />
                <TStat label="Park bookings" :value="fmt(stats.park_bookings.total)" />
                <TStat label="Revenue" :value="formatMoney(totalRevenue)" />
            </div>

            <!-- Anything needing a decision comes before the reading material. -->
            <div v-if="attention.length" class="space-y-2">
                <router-link
                    v-for="item in attention"
                    :key="item.key"
                    :to="item.to"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border px-4 py-3 text-sm transition-opacity hover:opacity-80"
                    :class="TONE_CLASS[item.tone]"
                >
                    <TIcon :name="item.icon" :size="16" />
                    <span class="font-medium">{{ item.label }}</span>
                    <span class="ml-auto inline-flex items-center gap-1 font-semibold">
                        {{ item.action }}
                        <TIcon name="arrowRight" :size="14" />
                    </span>
                </router-link>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <TCard>
                    <template #header>
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <h3 class="text-sm font-semibold text-foreground">Activity, last 30 days</h3>
                            <p class="text-xs text-foreground-muted">
                                {{ fmt(bookingsLast30) }} bookings taken
                            </p>
                        </div>
                    </template>
                    <template #headerAction>
                        <!-- Legend in HTML rather than on the canvas: identity is
                             carried by the swatch, and the label keeps text ink
                             rather than being tinted with the series colour. -->
                        <ul class="flex flex-wrap items-center gap-x-4 gap-y-1">
                            <li
                                v-for="s in SERIES"
                                :key="s.key"
                                class="flex items-center gap-1.5 text-xs text-foreground-secondary"
                            >
                                <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: s.color }" aria-hidden="true" />
                                {{ s.label }}
                            </li>
                        </ul>
                    </template>

                    <div class="h-72">
                        <Line :data="activityData" :options="activityOptions" />
                    </div>
                </TCard>

                <TCard title="Where people are">
                    <ul class="space-y-3">
                        <li v-for="row in roleRows" :key="row.key" class="flex items-center justify-between gap-3">
                            <TBadge :variant="row.variant">{{ row.label }}</TBadge>
                            <span class="text-lg font-semibold text-foreground [font-variant-numeric:tabular-nums]">
                                {{ fmt(row.count) }}
                            </span>
                        </li>
                        <li v-if="roleRows.length === 0" class="text-sm text-foreground-muted">No accounts yet.</li>
                    </ul>
                </TCard>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <TCard>
                    <template #header>
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <h3 class="text-sm font-semibold text-foreground">Revenue, last 30 days</h3>
                            <p class="text-xs text-foreground-muted">{{ formatMoney(revenueLast30) }} booked</p>
                        </div>
                    </template>
                    <!-- Single series, so no legend: the title names it. Kept on
                         its own chart rather than a second axis on the one above,
                         because money and counts share no scale. -->
                    <div class="h-56">
                        <Bar :data="revenueData" :options="revenueOptions" />
                    </div>
                </TCard>

                <TCard title="Status by module">
                    <div class="space-y-4">
                        <div v-for="mod in moduleRows" :key="mod.title">
                            <div class="mb-1.5 flex items-baseline justify-between gap-2">
                                <p class="text-sm font-medium text-foreground">{{ mod.title }}</p>
                                <p class="text-xs text-foreground-muted [font-variant-numeric:tabular-nums]">
                                    {{ fmt(mod.total) }}
                                </p>
                            </div>
                            <!-- 2px surface gaps between segments, per the mark
                                 spec, so touching fills stay legible. -->
                            <div v-if="mod.total > 0" class="flex h-2 gap-0.5 overflow-hidden">
                                <span
                                    v-for="part in mod.parts"
                                    :key="part.label"
                                    class="h-full rounded-sm first:rounded-l-sm last:rounded-r-sm"
                                    :class="part.class"
                                    :style="{ width: `${(part.value / mod.total) * 100}%` }"
                                    :title="`${part.label}: ${fmt(part.value)}`"
                                />
                            </div>
                            <div v-else class="h-2 rounded-sm bg-surface-hover" />
                            <p class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-foreground-muted">
                                <span v-for="part in mod.parts" :key="part.label" class="inline-flex items-center gap-1">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="part.class" aria-hidden="true" />
                                    {{ part.label }} {{ fmt(part.value) }}
                                </span>
                            </p>
                        </div>
                    </div>
                </TCard>
            </div>

        </div>
    </StaffLayout>
</template>
