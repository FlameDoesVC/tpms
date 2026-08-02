<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TStat from '@/Components/ui/TStat.vue';
import TBadge from '@/Components/ui/TBadge.vue';

const stats = ref(null);
const loading = ref(true);
const error = ref(null);

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
const money = (n) => '$' + Number(n ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader title="Admin Overview" icon="dashboard" compact />
        </template>

        <div v-if="loading" class="py-12 text-center text-sm text-foreground-muted">Loading…</div>
        <div v-else-if="error" class="py-12 text-center text-sm text-danger">{{ error }}</div>

        <div v-else class="max-w-5xl space-y-8">

            <!-- Top stats row -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <TStat label="Total Users" :value="fmt(stats.users.total)" />
                <TStat label="Hotel Bookings" :value="fmt(stats.hotel_bookings.total)" />
                <TStat label="Ferry Tickets" :value="fmt(stats.ferry_tickets.total)" />
                <TStat label="Park Bookings" :value="fmt(stats.park_bookings.total)" />
            </div>

            <!-- Users by role -->
            <TCard title="Users by Role" icon="users">
                <div class="flex flex-wrap gap-3">
                    <div
                        v-for="(count, role) in stats.users.by_role"
                        :key="role"
                        class="flex items-center gap-2 rounded-lg border px-4 py-3 bg-surface-hover"
                    >
                        <TBadge :variant="ROLE_VARIANT[role] ?? 'neutral'">{{ ROLE_LABELS[role] ?? role }}</TBadge>
                        <span class="text-lg font-semibold text-foreground">{{ fmt(count) }}</span>
                    </div>
                    <div v-if="stats.users.guests" class="flex items-center gap-2 rounded-lg border px-4 py-3 bg-surface-hover">
                        <span class="text-sm text-foreground-muted">Guest accounts</span>
                        <span class="text-lg font-semibold text-foreground">{{ fmt(stats.users.guests) }}</span>
                    </div>
                </div>
            </TCard>

            <!-- Module breakdowns -->
            <div class="grid gap-4 sm:grid-cols-3">

                <!-- Hotel -->
                <TCard title="Hotel Bookings" icon="bed">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Confirmed</dt>
                            <dd class="font-medium text-foreground">{{ fmt(stats.hotel_bookings.confirmed) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Pending</dt>
                            <dd class="font-medium text-foreground">{{ fmt(stats.hotel_bookings.pending) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Cancelled</dt>
                            <dd class="font-medium text-foreground">{{ fmt(stats.hotel_bookings.cancelled) }}</dd>
                        </div>
                        <div class="flex justify-between border-t pt-2">
                            <dt class="text-foreground-muted">Revenue</dt>
                            <dd class="font-semibold text-success">{{ money(stats.hotel_bookings.revenue) }}</dd>
                        </div>
                    </dl>
                </TCard>

                <!-- Ferry -->
                <TCard title="Ferry Tickets" icon="ferry">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Issued</dt>
                            <dd class="font-medium text-foreground">{{ fmt(stats.ferry_tickets.issued) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Used (boarded)</dt>
                            <dd class="font-medium text-foreground">{{ fmt(stats.ferry_tickets.used) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Cancelled</dt>
                            <dd class="font-medium text-foreground">{{ fmt(stats.ferry_tickets.cancelled) }}</dd>
                        </div>
                        <div class="flex justify-between border-t pt-2">
                            <dt class="text-foreground-muted">Total</dt>
                            <dd class="font-semibold text-foreground">{{ fmt(stats.ferry_tickets.total) }}</dd>
                        </div>
                    </dl>
                </TCard>

                <!-- Theme Park -->
                <TCard title="Theme Park" icon="sparkle">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Confirmed</dt>
                            <dd class="font-medium text-foreground">{{ fmt(stats.park_bookings.confirmed) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Used</dt>
                            <dd class="font-medium text-foreground">{{ fmt(stats.park_bookings.used) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-foreground-muted">Cancelled</dt>
                            <dd class="font-medium text-foreground">{{ fmt(stats.park_bookings.cancelled) }}</dd>
                        </div>
                        <div class="flex justify-between border-t pt-2">
                            <dt class="text-foreground-muted">Revenue</dt>
                            <dd class="font-semibold text-success">{{ money(stats.park_bookings.revenue) }}</dd>
                        </div>
                    </dl>
                </TCard>

            </div>
        </div>
    </StaffLayout>
</template>
