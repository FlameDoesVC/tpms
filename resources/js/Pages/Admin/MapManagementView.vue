<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TSwitch from '@/Components/ui/TSwitch.vue';

const locations = ref([]);
const loading = ref(true);
const showModal = ref(false);
const editing = ref(null);
const errors = ref({});
const mapRef = ref(null);
const placingPin = ref(false);

const TYPE_OPTIONS = [
    { value: 'hotel',     label: 'Hotel' },
    { value: 'ferry',     label: 'Ferry Terminal' },
    { value: 'themepark', label: 'Theme Park' },
    { value: 'beach',     label: 'Beach' },
    { value: 'general',   label: 'General' },
];

const TYPE_COLOR = {
    hotel:     '#22c55e',
    ferry:     '#f59e0b',
    themepark: '#3b82f6',
    beach:     '#06b6d4',
    general:   '#8b5cf6',
};

const emptyForm = () => ({
    name: '',
    description: '',
    type: 'general',
    position_top: 50,
    position_left: 50,
    is_active: true,
});
const form = ref(emptyForm());

const fetch = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/map/locations/manage');
        locations.value = data;
    } finally {
        loading.value = false;
    }
};

onMounted(fetch);

const openAdd = () => {
    editing.value = null;
    form.value = emptyForm();
    errors.value = {};
    showModal.value = true;
};

const openEdit = (loc) => {
    editing.value = loc;
    form.value = {
        name: loc.name,
        description: loc.description ?? '',
        type: loc.type,
        position_top: loc.position_top,
        position_left: loc.position_left,
        is_active: loc.is_active,
    };
    errors.value = {};
    showModal.value = true;
};

const save = async () => {
    errors.value = {};
    const payload = { ...form.value };
    if (!payload.description) delete payload.description;
    try {
        if (editing.value) {
            const { data } = await axios.patch(`/api/map/locations/${editing.value.id}`, payload);
            const i = locations.value.findIndex((l) => l.id === editing.value.id);
            if (i !== -1) locations.value[i] = data;
        } else {
            const { data } = await axios.post('/api/map/locations', payload);
            locations.value.push(data);
        }
        showModal.value = false;
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
    }
};

const toggleActive = async (loc) => {
    const { data } = await axios.patch(`/api/map/locations/${loc.id}`, { is_active: !loc.is_active });
    const i = locations.value.findIndex((l) => l.id === loc.id);
    if (i !== -1) locations.value[i] = data;
};

const remove = async (loc) => {
    if (confirm(`Delete "${loc.name}"?`)) {
        await axios.delete(`/api/map/locations/${loc.id}`);
        locations.value = locations.value.filter((l) => l.id !== loc.id);
    }
};

// Click-to-place: clicking on the map image sets position_top/position_left.
const onMapClick = (e) => {
    if (!mapRef.value) return;
    const rect = mapRef.value.getBoundingClientRect();
    const top = ((e.clientY - rect.top) / rect.height) * 100;
    const left = ((e.clientX - rect.left) / rect.width) * 100;
    form.value.position_top = Math.round(top * 10) / 10;
    form.value.position_left = Math.round(left * 10) / 10;
    placingPin.value = false;
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader title="Map Management" icon="map" compact />
        </template>

        <div class="max-w-5xl space-y-6">
            <TCard>
                <div class="flex items-center justify-between">
                    <p class="text-sm text-foreground-muted">
                        {{ locations.length }} location{{ locations.length === 1 ? '' : 's' }} on the map
                    </p>
                    <TButton @click="openAdd">+ Add Location</TButton>
                </div>
            </TCard>

            <!-- Live map preview -->
            <TCard title="Map Preview" icon="map">
                <p class="mb-3 text-xs text-foreground-muted">
                    Active pins shown below. Open a location and click "Place on map" to reposition it.
                </p>
                <div class="relative aspect-[900/869] w-full overflow-hidden rounded-lg bg-surface-hover">
                    <img
                        src="/images/velaafinolhu.png"
                        alt="Island map"
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                    <button
                        v-for="loc in locations.filter((l) => l.is_active)"
                        :key="loc.id"
                        type="button"
                        class="group absolute -translate-x-1/2 -translate-y-full"
                        :style="{ top: `${loc.position_top}%`, left: `${loc.position_left}%` }"
                        @click="openEdit(loc)"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            class="h-7 w-7 drop-shadow-md transition-transform group-hover:scale-125"
                            :style="{ color: TYPE_COLOR[loc.type] ?? '#8b5cf6' }"
                        >
                            <path d="M12 2C7.6 2 4 5.6 4 10c0 6 8 12 8 12s8-6 8-12c0-4.4-3.6-8-8-8zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" />
                        </svg>
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 w-36 -translate-x-1/2 rounded-md bg-gray-900/90 px-2 py-1 text-center text-xs text-white opacity-0 transition group-hover:opacity-100">
                            {{ loc.name }}
                        </div>
                    </button>
                </div>
            </TCard>

            <!-- Locations table -->
            <TCard title="All Locations" icon="map" :padding="false">
                <div v-if="loading" class="p-8 text-center text-sm text-foreground-muted">Loading…</div>
                <div v-else-if="locations.length === 0" class="p-4">
                    <TEmptyState title="No locations yet" description="Add a pin to start building the island map." icon="map" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[rgb(var(--color-border))] text-sm">
                        <thead>
                            <tr class="text-left text-foreground-muted">
                                <th class="p-4">Name</th>
                                <th class="p-4">Type</th>
                                <th class="p-4">Position</th>
                                <th class="p-4">Active</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgb(var(--color-border))] text-foreground-secondary">
                            <tr v-for="loc in locations" :key="loc.id">
                                <td class="p-4">
                                    <p class="font-medium text-foreground">{{ loc.name }}</p>
                                    <p v-if="loc.description" class="mt-0.5 max-w-xs truncate text-xs text-foreground-muted">{{ loc.description }}</p>
                                </td>
                                <td class="p-4">
                                    <TBadge variant="neutral">{{ loc.type }}</TBadge>
                                </td>
                                <td class="p-4 text-xs font-mono">{{ loc.position_top }}%, {{ loc.position_left }}%</td>
                                <td class="p-4">
                                    <button type="button" @click="toggleActive(loc)">
                                        <TBadge :variant="loc.is_active ? 'success' : 'neutral'">
                                            {{ loc.is_active ? 'Visible' : 'Hidden' }}
                                        </TBadge>
                                    </button>
                                </td>
                                <td class="p-4 space-x-2">
                                    <button class="text-sm text-primary hover:underline" @click="openEdit(loc)">Edit</button>
                                    <button class="text-sm text-danger hover:underline" @click="remove(loc)">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>
        </div>

        <TModal v-model:show="showModal" @close="showModal = false">
            <template #title>{{ editing ? 'Edit Location' : 'Add Location' }}</template>

            <form @submit.prevent="save" class="space-y-4">
                <TInput id="name" v-model="form.name" label="Name" :error="errors.name?.[0]" />

                <div>
                    <label class="mb-1 block text-sm font-medium text-foreground">Description (optional)</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="w-full rounded-lg border border-[rgb(var(--color-border))] bg-surface px-3 py-2 text-sm text-foreground placeholder:text-foreground-muted focus:outline-none focus:ring-2 focus:ring-primary/40"
                    />
                </div>

                <TSelect v-model="form.type" label="Type" :options="TYPE_OPTIONS" :error="errors.type?.[0]" />

                <div class="grid grid-cols-2 gap-4">
                    <TInput
                        id="position_top"
                        v-model.number="form.position_top"
                        label="Top (%)"
                        type="number"
                        min="0"
                        max="100"
                        step="0.1"
                        :error="errors.position_top?.[0]"
                    />
                    <TInput
                        id="position_left"
                        v-model.number="form.position_left"
                        label="Left (%)"
                        type="number"
                        min="0"
                        max="100"
                        step="0.1"
                        :error="errors.position_left?.[0]"
                    />
                </div>

                <!-- Click-to-place map -->
                <div>
                    <p class="mb-1.5 text-sm font-medium text-foreground">
                        Click on the map to place the pin
                        <span class="ml-1 text-xs text-foreground-muted">(top {{ form.position_top }}%, left {{ form.position_left }}%)</span>
                    </p>
                    <div
                        ref="mapRef"
                        class="relative aspect-[900/869] w-full cursor-crosshair overflow-hidden rounded-lg"
                        @click="onMapClick"
                    >
                        <img src="/images/velaafinolhu.png" alt="Island map" class="absolute inset-0 h-full w-full object-cover" />
                        <!-- Preview pin -->
                        <div
                            class="pointer-events-none absolute -translate-x-1/2 -translate-y-full"
                            :style="{ top: `${form.position_top}%`, left: `${form.position_left}%` }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                class="h-8 w-8 drop-shadow-lg"
                                :style="{ color: TYPE_COLOR[form.type] ?? '#8b5cf6' }"
                            >
                                <path d="M12 2C7.6 2 4 5.6 4 10c0 6 8 12 8 12s8-6 8-12c0-4.4-3.6-8-8-8zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <TSwitch v-model="form.is_active" />
                    <span class="text-sm text-foreground">Visible on map</span>
                </div>

                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="showModal = false">Cancel</TButton>
                <TButton type="button" @click="save">Save</TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
