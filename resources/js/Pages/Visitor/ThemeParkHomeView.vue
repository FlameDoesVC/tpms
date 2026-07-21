<script setup>
import { computed, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useThemeParkStore } from '@/stores/themepark';

const themeParkStore = useThemeParkStore();
const tab = ref('ride');

const tabs = [
    { key: 'ride', label: 'Rides' },
    { key: 'show', label: 'Shows' },
    { key: 'beach_event', label: 'Beach Events' },
];

onMounted(() => themeParkStore.fetchEvents());

const eventsForTab = computed(() =>
    themeParkStore.events.filter((e) => e.type === tab.value)
);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Theme Park
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex gap-2 border-b border-gray-200">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        @click="tab = t.key"
                        class="px-4 py-2 text-sm font-medium"
                        :class="tab === t.key
                            ? 'border-b-2 border-indigo-600 text-indigo-600'
                            : 'text-gray-500 hover:text-gray-700'"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <div v-if="themeParkStore.loading.events" class="text-gray-500">Loading events...</div>
                <div v-else-if="eventsForTab.length === 0" class="text-gray-500">No events in this category.</div>

                <div v-else class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <router-link
                        v-for="event in eventsForTab"
                        :key="event.id"
                        :to="{ name: 'themepark.event', params: { id: event.id } }"
                        class="block overflow-hidden rounded-lg bg-white shadow transition hover:shadow-md"
                    >
                        <div class="flex h-32 items-center justify-center bg-gray-100 text-gray-400">
                            <img v-if="event.image_url" :src="event.image_url" :alt="event.name" class="h-full w-full object-cover" />
                            <span v-else>No image</span>
                        </div>
                        <div class="p-4">
                            <h3 class="font-semibold text-gray-900">{{ event.name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ event.duration_minutes }} min - {{ event.location }}</p>
                            <p class="mt-2 text-sm font-medium text-gray-900">${{ event.price_per_ticket }} / ticket</p>
                        </div>
                    </router-link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
