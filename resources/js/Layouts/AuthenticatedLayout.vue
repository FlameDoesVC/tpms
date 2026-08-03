<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import TDropdown from '@/Components/ui/TDropdown.vue';
import TDropdownLink from '@/Components/ui/TDropdownLink.vue';
import TNavLink from '@/Components/ui/TNavLink.vue';
import TResponsiveNavLink from '@/Components/ui/TResponsiveNavLink.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TAvatar from '@/Components/ui/TAvatar.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import PaymentsDueMenu from '@/Components/PaymentsDueMenu.vue';
import CartHeaderButton from '@/Components/CartHeaderButton.vue';
import CartDockedPanel from '@/Components/CartDockedPanel.vue';
import { useAuthStore } from '@/stores/auth';
import { useHotelStore } from '@/stores/hotel';
import { useTheme } from '@/composables/useTheme';

const showingNavigationDropdown = ref(false);
const auth = useAuthStore();
const hotelStore = useHotelStore();
const route = useRoute();
const router = useRouter();
const { isDark, toggle: toggleTheme } = useTheme();

const showCart = computed(() =>
    (!auth.isAuthenticated || auth.userRole === 'visitor') && route.name !== 'cart.checkout'
);

onMounted(() => {
    if (auth.isAuthenticated && auth.userRole === 'visitor') hotelStore.fetchMyBookings({ silent: true });
});

const linksByRole = {
    visitor: [
        { label: 'Dashboard', name: 'dashboard', icon: 'dashboard', authOnly: true },
        { label: 'Theme Park', name: 'themepark.home', icon: 'sparkle' },
        { label: 'Hotels', name: 'hotels.index', icon: 'hotel' },
        { label: 'Ferry', name: 'ferry.book', icon: 'ferry' },
        { label: 'My Trips', name: 'trips', icon: 'ticket', authOnly: true },
    ],
    hotel_manager: [
        { label: 'Dashboard', name: 'dashboard', icon: 'dashboard' },
        { label: 'Hotel Bookings', name: 'manager.hotel-dashboard', icon: 'calendar' },
        { label: 'Rooms', name: 'manager.rooms', icon: 'bed' },
    ],
    ferry_operator: [
        { label: 'Dashboard', name: 'dashboard', icon: 'dashboard' },
        { label: 'Schedules', name: 'ferry.schedule-management', icon: 'calendar' },
        { label: 'Validate Ticket', name: 'ferry.validate', icon: 'scan' },
        { label: 'Passengers', name: 'ferry.passengers', icon: 'users' },
    ],
    themepark_staff: [
        { label: 'Dashboard', name: 'dashboard', icon: 'dashboard' },
        { label: 'Events', name: 'themepark.event-management', icon: 'sparkle' },
        { label: 'Slots', name: 'themepark.slot-scheduling', icon: 'calendar' },
        { label: 'Capacity', name: 'themepark.capacity', icon: 'capacity' },
        { label: 'Validate Ticket', name: 'themepark.validate', icon: 'scan' },
        { label: 'Walk-in Sales', name: 'themepark.walkin-sales', icon: 'ticket' },
        { label: 'Sales Report', name: 'themepark.sales-report', icon: 'report' },
    ],
    admin: [{ label: 'Dashboard', name: 'dashboard', icon: 'dashboard' }],
};

const navLinks = computed(() => {
    const links = linksByRole[auth.userRole] ?? linksByRole.visitor;
    return auth.isAuthenticated ? links : links.filter((link) => !link.authOnly);
});

const logout = async () => {
    await auth.logout();
    router.push({ name: 'login' });
};
</script>

<template>
    <div>
        <div class="min-h-screen bg-page">
            <nav class="tide-line sticky top-0 z-30 border-b bg-surface/85 shadow-xs backdrop-blur-md">
                <div class="shell">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <div class="flex shrink-0 items-center">
                                <router-link :to="{ name: 'welcome' }">
                                    <ApplicationLogo />
                                </router-link>
                            </div>

                            <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <TNavLink
                                    v-for="link in navLinks"
                                    :key="link.name"
                                    :to="{ name: link.name }"
                                    :active="route.name === link.name"
                                >
                                    <TIcon v-if="link.icon" :name="link.icon" :size="16" />
                                    {{ link.label }}
                                </TNavLink>
                            </div>
                        </div>

                        <div v-if="auth.isAuthenticated" class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-3">
                            <button
                                type="button"
                                @click="toggleTheme"
                                class="rounded-lg p-2 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                                :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                            >
                                <TIcon :name="isDark ? 'sun' : 'moon'" :size="19" />
                            </button>

                            <TBadge v-if="auth.userRole" variant="primary">
                                {{ auth.isGuest ? 'guest' : auth.userRole.replace('_', ' ') }}
                            </TBadge>

                            <PaymentsDueMenu v-if="auth.userRole === 'visitor'" />
                            <CartHeaderButton v-if="showCart" />

                            <div class="relative ms-1">
                                <TDropdown align="right" width="48">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-foreground-secondary transition-colors hover:bg-surface-hover hover:text-foreground focus:outline-none"
                                        >
                                            <TAvatar :name="auth.user?.name ?? ''" size="sm" />
                                            <span class="hidden md:inline">{{ auth.user?.name }}</span>
                                            <svg class="h-4 w-4 text-foreground-muted" fill="none" viewBox="0 0 20 20">
                                                <path fill="currentColor" fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </template>

                                    <template #content>
                                        <TDropdownLink v-if="!auth.isGuest" :to="{ name: 'profile.edit' }">Profile</TDropdownLink>
                                        <TDropdownLink v-if="auth.userRole === 'visitor'" :to="{ name: 'trips' }">My Trips</TDropdownLink>
                                        <TDropdownLink v-if="auth.isGuest" :to="{ name: 'login', query: { redirect: route.fullPath } }">Log In</TDropdownLink>
                                        <TDropdownLink v-if="auth.isGuest" :to="{ name: 'register', query: { redirect: route.fullPath } }">Sign Up</TDropdownLink>
                                        <div class="my-1 border-t" />
                                        <button
                                            @click="logout"
                                            class="block w-full px-4 py-2 text-left text-sm text-foreground-secondary transition-colors hover:bg-surface-hover hover:text-foreground"
                                        >
                                            Log Out
                                        </button>
                                    </template>
                                </TDropdown>
                            </div>
                        </div>

                        <div v-else class="hidden sm:ms-6 sm:flex sm:items-center sm:gap-3">
                            <button
                                type="button"
                                @click="toggleTheme"
                                class="rounded-lg p-2 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground"
                            >
                                <TIcon :name="isDark ? 'sun' : 'moon'" :size="19" />
                            </button>
                            <CartHeaderButton v-if="showCart" />
                            <router-link :to="{ name: 'login' }" class="text-sm font-medium text-foreground-secondary hover:text-foreground">
                                Log in
                            </router-link>
                            <router-link
                                :to="{ name: 'register' }"
                                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover"
                            >
                                Register
                            </router-link>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                                class="inline-flex items-center justify-center rounded-lg p-2 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-foreground focus:outline-none"
                            >
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        :class="{ hidden: showingNavigationDropdown, 'inline-flex': !showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{ hidden: !showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                        stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }" class="sm:hidden">
                    <div class="space-y-1 pb-3 pt-2">
                        <TResponsiveNavLink
                            v-for="link in navLinks"
                            :key="link.name"
                            :to="{ name: link.name }"
                            :active="route.name === link.name"
                        >
                            {{ link.label }}
                        </TResponsiveNavLink>
                    </div>

                    <div v-if="auth.isAuthenticated" class="border-t pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-foreground">{{ auth.user?.name }}</div>
                            <div class="text-sm font-medium text-foreground-muted">
                                {{ auth.isGuest ? 'Guest checkout' : auth.user?.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <TResponsiveNavLink v-if="!auth.isGuest" :to="{ name: 'profile.edit' }">Profile</TResponsiveNavLink>
                            <TResponsiveNavLink v-if="auth.userRole === 'visitor'" :to="{ name: 'trips' }">My Trips</TResponsiveNavLink>
                            <TResponsiveNavLink v-if="auth.userRole === 'visitor'" :to="{ name: 'cart.checkout' }">Cart</TResponsiveNavLink>
                            <TResponsiveNavLink v-if="auth.isGuest" :to="{ name: 'login', query: { redirect: route.fullPath } }">Log In</TResponsiveNavLink>
                            <TResponsiveNavLink v-if="auth.isGuest" :to="{ name: 'register', query: { redirect: route.fullPath } }">Sign Up</TResponsiveNavLink>
                            <button
                                @click="logout"
                                class="block w-full border-l-4 border-transparent py-2 pe-4 ps-3 text-left text-base font-medium text-foreground-secondary transition-colors hover:border-foreground-muted/30 hover:bg-surface-hover hover:text-foreground"
                            >
                                Log Out
                            </button>
                        </div>
                    </div>
                    <div v-else class="border-t pb-1 pt-4">
                        <div class="mt-3 space-y-1">
                            <TResponsiveNavLink :to="{ name: 'cart.checkout' }">Cart</TResponsiveNavLink>
                            <TResponsiveNavLink :to="{ name: 'login' }">Log in</TResponsiveNavLink>
                            <TResponsiveNavLink :to="{ name: 'register' }">Register</TResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="lg:flex lg:items-start">
                <div class="min-w-0 flex-1">
                    <header v-if="$slots.header" class="depth-gradient border-b">
                        <div class="shell py-6">
                            <slot name="header" />
                        </div>
                    </header>

                    <main>
                        <slot />
                    </main>
                </div>

                <CartDockedPanel v-if="showCart" />
            </div>
        </div>
    </div>
</template>
