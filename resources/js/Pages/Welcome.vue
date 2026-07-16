<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-100">
        <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />

        <h1 class="mt-6 text-3xl font-semibold text-gray-900">
            Theme Park Management System
        </h1>

        <p class="mt-2 text-gray-600">
            Book hotels, ferry tickets, and theme park events for your island visit.
        </p>

        <div class="mt-8 flex gap-4">
            <template v-if="auth.isAuthenticated">
                <router-link
                    :to="{ name: 'dashboard' }"
                    class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                >
                    Dashboard
                </router-link>
            </template>
            <template v-else>
                <router-link
                    :to="{ name: 'login' }"
                    class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                >
                    Log in
                </router-link>
                <router-link
                    :to="{ name: 'register' }"
                    class="rounded-md border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Register
                </router-link>
            </template>
        </div>
    </div>
</template>
