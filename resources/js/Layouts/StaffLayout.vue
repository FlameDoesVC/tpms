<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import TIcon from '@/Components/ui/TIcon.vue';
import TAvatar from '@/Components/ui/TAvatar.vue';
import TDropdown from '@/Components/ui/TDropdown.vue';
import TDropdownLink from '@/Components/ui/TDropdownLink.vue';
import { useAuthStore } from '@/stores/auth';
import { useTheme } from '@/composables/useTheme';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { isDark, toggle: toggleTheme } = useTheme();

// Persisted so the choice survives navigation; ops staff live in here all day.
const collapsed = ref(localStorage.getItem('staffNavCollapsed') === '1');
const mobileOpen = ref(false);

const toggleCollapsed = () => {
    collapsed.value = !collapsed.value;
    localStorage.setItem('staffNavCollapsed', collapsed.value ? '1' : '0');
};

const navByRole = {
    hotel_manager: [
        { section: 'Front desk', items: [
            { label: 'Overview', name: 'dashboard', icon: 'dashboard' },
            { label: 'Bookings', name: 'manager.hotel-dashboard', icon: 'calendar' },
            { label: 'Rooms', name: 'manager.rooms', icon: 'bed' },
        ] },
        { section: 'Marketing', items: [
            { label: 'Promotions', name: 'promotions', icon: 'sparkle' },
        ] },
    ],
    ferry_operator: [
        { section: 'Operations', items: [
            { label: 'Overview', name: 'dashboard', icon: 'dashboard' },
            { label: 'Fleet', name: 'ferry.fleet', icon: 'ferry' },
            { label: 'Schedules', name: 'ferry.schedule-management', icon: 'calendar' },
            { label: 'Passengers', name: 'ferry.passengers', icon: 'users' },
        ] },
        { section: 'At the dock', items: [
            { label: 'Validate', name: 'ferry.validate', icon: 'scan' },
        ] },
        { section: 'Marketing', items: [
            { label: 'Promotions', name: 'promotions', icon: 'sparkle' },
        ] },
    ],
    themepark_staff: [
        { section: 'Programme', items: [
            { label: 'Overview', name: 'dashboard', icon: 'dashboard' },
            { label: 'Events', name: 'themepark.event-management', icon: 'sparkle' },
            { label: 'Slots', name: 'themepark.slot-scheduling', icon: 'calendar' },
            { label: 'Capacity', name: 'themepark.capacity', icon: 'capacity' },
        ] },
        { section: 'At the gate', items: [
            { label: 'Validate', name: 'themepark.validate', icon: 'scan' },
            { label: 'Walk-in sales', name: 'themepark.walkin-sales', icon: 'ticket' },
        ] },
        { section: 'Insight', items: [
            { label: 'Sales report', name: 'themepark.sales-report', icon: 'report' },
        ] },
        { section: 'Marketing', items: [
            { label: 'Promotions', name: 'promotions', icon: 'sparkle' },
        ] },
    ],
    admin: [
        { section: 'Admin', items: [
            { label: 'Overview', name: 'admin.dashboard', icon: 'dashboard' },
            { label: 'Users', name: 'admin.users', icon: 'users' },
            { label: 'Promotions', name: 'promotions', icon: 'sparkle' },
            { label: 'Island Map', name: 'admin.map', icon: 'map' },
        ] },
        { section: 'Hotel', items: [
            { label: 'Hotels', name: 'admin.hotels', icon: 'hotel' },
            { label: 'Bookings', name: 'manager.hotel-dashboard', icon: 'calendar' },
            { label: 'Rooms', name: 'manager.rooms', icon: 'bed' },
        ] },
        { section: 'Ferry', items: [
            { label: 'Fleet', name: 'ferry.fleet', icon: 'ferry' },
            { label: 'Schedules', name: 'ferry.schedule-management', icon: 'calendar' },
            { label: 'Passengers', name: 'ferry.passengers', icon: 'users' },
            { label: 'Validate', name: 'ferry.validate', icon: 'scan' },
        ] },
        { section: 'Theme Park', items: [
            { label: 'Events', name: 'themepark.event-management', icon: 'sparkle' },
            { label: 'Slots', name: 'themepark.slot-scheduling', icon: 'calendar' },
            { label: 'Capacity', name: 'themepark.capacity', icon: 'capacity' },
            { label: 'Validate', name: 'themepark.validate', icon: 'scan' },
            { label: 'Walk-in sales', name: 'themepark.walkin-sales', icon: 'ticket' },
            { label: 'Sales report', name: 'themepark.sales-report', icon: 'report' },
        ] },
    ],
};

const groups = computed(() => navByRole[auth.userRole] ?? navByRole.admin);
const roleLabel = computed(() => (auth.userRole ?? '').replace(/_/g, ' '));

const logout = async () => {
    await auth.logout();
    router.push({ name: 'login' });
};
</script>

<template>
    <div class="min-h-screen bg-page">
        <!-- Mobile scrim -->
        <div
            v-if="mobileOpen"
            class="scrim fixed inset-0 z-30 backdrop-blur-sm lg:hidden"
            @click="mobileOpen = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-40 flex flex-col border-r bg-surface transition-[width,transform] duration-200 lg:translate-x-0"
            :class="[
                collapsed ? 'w-[4.25rem]' : 'w-60',
                mobileOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Brand -->
            <div class="flex h-16 items-center gap-2.5 border-b px-4">
                <router-link :to="{ name: 'welcome' }" class="flex items-center gap-2.5 overflow-hidden">
                    <img src="/images/logo.png" alt="TPMS" class="h-8 w-8 shrink-0 object-contain" />
                    <span v-if="!collapsed" class="truncate text-base font-bold tracking-tight text-foreground">
                        TPMS<span class="text-accent">.</span>
                    </span>
                </router-link>
            </div>

            <nav class="flex-1 overflow-y-auto px-2.5 py-4">
                <div v-for="group in groups" :key="group.section" class="mb-5">
                    <p
                        v-if="!collapsed"
                        class="mb-1.5 px-2.5 text-[0.6875rem] font-semibold uppercase tracking-wider text-foreground-muted"
                    >
                        {{ group.section }}
                    </p>
                    <ul class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.name">
                            <router-link
                                :to="{ name: item.name }"
                                :title="collapsed ? item.label : undefined"
                                class="group relative flex items-center gap-3 rounded px-2.5 py-2 text-sm font-medium transition-colors"
                                :class="route.name === item.name
                                    ? 'bg-primary-soft text-primary'
                                    : 'text-foreground-secondary hover:bg-surface-hover hover:text-foreground'"
                                @click="mobileOpen = false"
                            >
                                <!-- Active rail: reads at a glance when collapsed -->
                                <span
                                    v-if="route.name === item.name"
                                    class="absolute inset-y-1 left-0 w-0.5 rounded-full bg-primary"
                                />
                                <TIcon :name="item.icon" :size="18" />
                                <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
                            </router-link>
                        </li>
                    </ul>
                </div>
            </nav>

            <div class="border-t p-2.5">
                <button
                    type="button"
                    class="hidden w-full items-center gap-3 rounded px-2.5 py-2 text-sm text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground lg:flex"
                    @click="toggleCollapsed"
                >
                    <TIcon :name="collapsed ? 'chevronRight' : 'arrowLeft'" :size="18" />
                    <span v-if="!collapsed">Collapse</span>
                </button>
            </div>
        </aside>

        <div :class="collapsed ? 'lg:pl-[4.25rem]' : 'lg:pl-60'" class="transition-[padding] duration-200">
            <header class="sticky top-0 z-20 border-b bg-surface/85 backdrop-blur-md">
                <!-- Same container as <main>, so the page title sits on the
                     same left edge as the content it belongs to. -->
                <div class="shell flex h-16 items-center gap-3">
                    <button
                        type="button"
                        class="rounded p-2 text-foreground-muted hover:bg-surface-hover hover:text-foreground lg:hidden"
                        @click="mobileOpen = true"
                    >
                        <TIcon name="menu" :size="20" />
                    </button>

                    <div class="min-w-0 flex-1">
                        <slot name="header" />
                    </div>

                    <span class="hidden rounded bg-surface-hover px-2 py-1 text-xs font-medium capitalize text-foreground-secondary sm:inline">
                        {{ roleLabel }}
                    </span>

                    <button
                        type="button"
                        class="rounded p-2 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                        @click="toggleTheme"
                    >
                        <TIcon :name="isDark ? 'sun' : 'moon'" :size="19" />
                    </button>

                    <TDropdown align="right" width="48">
                        <template #trigger>
                            <button type="button" class="flex items-center gap-2 rounded p-1 hover:bg-surface-hover">
                                <TAvatar :name="auth.user?.name ?? ''" size="sm" />
                            </button>
                        </template>
                        <template #content>
                            <div class="border-b px-4 py-2.5">
                                <p class="truncate text-sm font-medium text-foreground">{{ auth.user?.name }}</p>
                                <p class="truncate text-xs text-foreground-muted">{{ auth.user?.email }}</p>
                            </div>
                            <TDropdownLink :to="{ name: 'profile.edit' }">Profile</TDropdownLink>
                            <button
                                class="block w-full px-4 py-2 text-left text-sm text-foreground-secondary transition-colors hover:bg-surface-hover hover:text-foreground"
                                @click="logout"
                            >
                                Log out
                            </button>
                        </template>
                    </TDropdown>
                </div>
            </header>

            <!-- Same container as the visitor side. Staff screens were each
                 capping themselves at max-w-5xl inside an already sidebar-inset
                 area, so a 1600px monitor showed a 1024px column with wide
                 gutters on both sides — the exact complaint the visitor pages
                 had. Screens that genuinely want a narrow measure (a scanner, a
                 single form) now say so themselves rather than every screen
                 being narrow by default. -->
            <main class="shell py-6">
                <slot />
            </main>
        </div>
    </div>
</template>
