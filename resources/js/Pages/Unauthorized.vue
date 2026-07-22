<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Unauthorized</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-12 text-center sm:px-6 lg:px-8">
            <p class="text-gray-600">You don't have permission to perform that action.</p>
            <router-link :to="{ name: 'dashboard' }" class="mt-4 inline-block text-indigo-600 hover:underline">
                Back to dashboard
            </router-link>
        </div>
    </AuthenticatedLayout>
</template>
