<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { useAuthStore } from '@/stores/auth';

const showingNavigationDropdown = ref(false);
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

// Links per role; routes get added here as the matching views are built.
const linksByRole = {
    visitor: [
        { label: 'Dashboard', name: 'dashboard' },
        { label: 'Hotels', name: 'hotels.index' },
        { label: 'Ferry', name: 'ferry.book' },
        { label: 'My Tickets', name: 'ferry.my-tickets' },
        { label: 'Theme Park', name: 'themepark.home' },
        { label: 'My Park Bookings', name: 'themepark.my-bookings' },
    ],
    hotel_manager: [
        { label: 'Dashboard', name: 'dashboard' },
        { label: 'Hotel Bookings', name: 'manager.hotel-dashboard' },
        { label: 'Rooms', name: 'manager.rooms' },
    ],
    ferry_operator: [
        { label: 'Dashboard', name: 'dashboard' },
        { label: 'Schedules', name: 'ferry.schedule-management' },
        { label: 'Validate Ticket', name: 'ferry.validate' },
        { label: 'Passengers', name: 'ferry.passengers' },
    ],
    themepark_staff: [
        { label: 'Dashboard', name: 'dashboard' },
        { label: 'Events', name: 'themepark.event-management' },
        { label: 'Slots', name: 'themepark.slot-scheduling' },
        { label: 'Capacity', name: 'themepark.capacity' },
        { label: 'Validate Ticket', name: 'themepark.validate' },
        { label: 'Walk-in Sales', name: 'themepark.walkin-sales' },
        { label: 'Sales Report', name: 'themepark.sales-report' },
    ],
    admin: [{ label: 'Dashboard', name: 'dashboard' }],
};

const navLinks = computed(() => linksByRole[auth.userRole] ?? []);

const logout = async () => {
    await auth.logout();
    router.push({ name: 'login' });
};
</script>

<template>
    <div>
        <div class="min-h-screen bg-gray-100">
            <nav class="border-b border-gray-100 bg-white">
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <router-link :to="{ name: 'dashboard' }">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </router-link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    v-for="link in navLinks"
                                    :key="link.name"
                                    :to="{ name: link.name }"
                                    :active="route.name === link.name"
                                >
                                    {{ link.label }}
                                </NavLink>
                            </div>
                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <span
                                v-if="auth.userRole"
                                class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-medium text-indigo-800"
                            >
                                {{ auth.userRole.replace('_', ' ') }}
                            </span>

                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ auth.user?.name }}

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :to="{ name: 'profile.edit' }"
                                        >
                                            Profile
                                        </DropdownLink>
                                        <button
                                            @click="logout"
                                            class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                                        >
                                            Log Out
                                        </button>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink
                            v-for="link in navLinks"
                            :key="link.name"
                            :to="{ name: link.name }"
                            :active="route.name === link.name"
                        >
                            {{ link.label }}
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">
                                {{ auth.user?.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ auth.user?.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :to="{ name: 'profile.edit' }">
                                Profile
                            </ResponsiveNavLink>
                            <button
                                @click="logout"
                                class="block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 focus:outline-none"
                            >
                                Log Out
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header class="bg-white shadow" v-if="$slots.header">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
