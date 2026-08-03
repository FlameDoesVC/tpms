<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TSwitch from '@/Components/ui/TSwitch.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';

const locations = ref([]);
const loading = ref(true);
const showModal = ref(false);
const editing = ref(null);
const errors = ref({});
const saving = ref(false);
const mapRef = ref(null);
const confirm = useConfirm();

const TYPE_OPTIONS = [
    { value: 'hotel',     label: 'Hotel' },
    { value: 'ferry',     label: 'Ferry Terminal' },
    { value: 'themepark', label: 'Theme Park' },
    { value: 'beach',     label: 'Beach' },
    { value: 'general',   label: 'General' },
];

// Category colours, not theme tokens: a pin's colour is what identifies what
// kind of place it is, and it has to stay the same in both themes and match
// the visitor-facing IslandMap exactly.
const TYPE_COLOR = {
    hotel:     '#22c55e',
    ferry:     '#f59e0b',
    themepark: '#3b82f6',
    beach:     '#06b6d4',
    general:   '#8b5cf6',
};

const TYPE_LABELS = Object.fromEntries(TYPE_OPTIONS.map((o) => [o.value, o.label]));

const emptyForm = () => ({
    name: '',
    description: '',
    type: 'general',
    position_top: 50,
    position_left: 50,
    is_active: true,
});
const form = ref(emptyForm());

const activeLocations = computed(() => locations.value.filter((l) => l.is_active));

const summary = computed(() => {
    const total = locations.value.length;
    if (!total) return null;
    const shown = activeLocations.value.length;
    return `${total} location${total === 1 ? '' : 's'} · ${shown} visible`;
});

// Named `load`, not `fetch` — the original shadowed the global inside this
// module, so any later use of window.fetch here would have silently resolved
// to the loader instead.
const load = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/map/locations/manage');
        locations.value = data;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not load map locations.');
    } finally {
        loading.value = false;
    }
};

onMounted(load);

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
    saving.value = true;
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
        showToast(editing.value ? 'Location updated.' : 'Location added.', 'success');
        showModal.value = false;
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        if (Object.keys(errors.value).length === 0) {
            showToast(e.response?.data?.message ?? 'Could not save this location.');
        }
    } finally {
        saving.value = false;
    }
};

const toggleActive = async (loc) => {
    try {
        const { data } = await axios.patch(`/api/map/locations/${loc.id}`, { is_active: !loc.is_active });
        const i = locations.value.findIndex((l) => l.id === loc.id);
        if (i !== -1) locations.value[i] = data;
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not change visibility.');
    }
};

const remove = async (loc) => {
    const ok = await confirm({
        title: `Delete "${loc.name}"?`,
        message: 'The pin is removed from the island map for good.',
        confirmLabel: 'Delete pin',
        danger: true,
    });
    if (!ok) return;

    try {
        await axios.delete(`/api/map/locations/${loc.id}`);
        locations.value = locations.value.filter((l) => l.id !== loc.id);
        showToast('Location deleted.', 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not delete this location.');
    }
};

// Click-to-place: clicking the map image sets position_top/position_left.
const onMapClick = (e) => {
    if (!mapRef.value) return;
    const rect = mapRef.value.getBoundingClientRect();
    const top = ((e.clientY - rect.top) / rect.height) * 100;
    const left = ((e.clientX - rect.left) / rect.width) * 100;
    form.value.position_top = Math.round(top * 10) / 10;
    form.value.position_left = Math.round(left * 10) / 10;
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader title="Map Management" icon="map" compact />
        </template>

        <div class="space-y-5">
            <StaffToolbar title="Island map" :summary="summary">
                <template #actions>
                    <TButton @click="openAdd">
                        <TIcon name="plus" :size="16" />
                        Add location
                    </TButton>
                </template>
            </StaffToolbar>

            <!-- Map beside the list, not stacked above it. The island image is
                 all but square, so at full page width a stacked preview became a
                 wall you had to scroll past before reaching the thing you came
                 to edit. Side by side, a pin and its row are visible together —
                 which is the whole job of this screen. -->
            <div class="grid gap-5 xl:grid-cols-[minmax(0,32rem)_minmax(0,1fr)]">
                <div class="xl:sticky xl:top-20 xl:self-start">
                    <TCard title="Live preview" icon="map">
                        <p class="mb-3 text-xs text-foreground-muted">
                            Visible pins only. Click one to edit it, or drop a new pin from inside the editor.
                        </p>
                        <div class="relative aspect-[900/869] w-full overflow-hidden rounded-lg bg-surface-hover">
                            <img
                                src="/images/velaafinolhu.png"
                                alt="Island map"
                                class="absolute inset-0 h-full w-full object-cover"
                            />
                            <button
                                v-for="loc in activeLocations"
                                :key="loc.id"
                                type="button"
                                class="group absolute -translate-x-1/2 -translate-y-full focus-visible:outline-none"
                                :style="{ top: `${loc.position_top}%`, left: `${loc.position_left}%` }"
                                :aria-label="`Edit ${loc.name}`"
                                @click="openEdit(loc)"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    class="h-7 w-7 drop-shadow-md transition-transform group-hover:scale-125 group-focus-visible:scale-125"
                                    :style="{ color: TYPE_COLOR[loc.type] ?? TYPE_COLOR.general }"
                                >
                                    <path d="M12 2C7.6 2 4 5.6 4 10c0 6 8 12 8 12s8-6 8-12c0-4.4-3.6-8-8-8zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" />
                                </svg>
                                <span
                                    class="elevated pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 w-36 -translate-x-1/2 rounded-lg border bg-surface px-2 py-1 text-center text-xs text-foreground opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100"
                                >
                                    {{ loc.name }}
                                </span>
                            </button>
                        </div>
                    </TCard>
                </div>

                <TCard title="All locations" icon="pin" :padding="false">
                    <div v-if="loading" class="p-8 text-center text-sm text-foreground-muted">Loading locations…</div>
                    <div v-else-if="locations.length === 0" class="p-4">
                        <TEmptyState title="No locations yet" description="Add a pin to start building the island map." icon="map">
                            <template #action>
                                <TButton @click="openAdd">Add location</TButton>
                            </template>
                        </TEmptyState>
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Position</th>
                                    <th>Visible</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="loc in locations" :key="loc.id">
                                    <td>
                                        <div class="flex items-start gap-2.5">
                                            <span
                                                class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                                                :style="{ backgroundColor: TYPE_COLOR[loc.type] ?? TYPE_COLOR.general }"
                                                aria-hidden="true"
                                            />
                                            <div class="min-w-0">
                                                <p class="font-medium text-foreground">{{ loc.name }}</p>
                                                <p v-if="loc.description" class="mt-0.5 max-w-xs truncate text-xs text-foreground-muted">
                                                    {{ loc.description }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td><TBadge variant="neutral">{{ TYPE_LABELS[loc.type] ?? loc.type }}</TBadge></td>
                                    <td class="whitespace-nowrap font-mono text-xs">
                                        {{ loc.position_top }}% / {{ loc.position_left }}%
                                    </td>
                                    <td>
                                        <!-- A switch, not a badge wrapped in a bare button:
                                             the old control looked like a status label and
                                             gave no hint it could be clicked. -->
                                        <TSwitch
                                            :model-value="loc.is_active"
                                            @update:model-value="toggleActive(loc)"
                                        />
                                    </td>
                                    <td>
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-primary"
                                                :aria-label="`Edit ${loc.name}`"
                                                title="Edit"
                                                @click="openEdit(loc)"
                                            >
                                                <TIcon name="edit" :size="16" />
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                                                :aria-label="`Delete ${loc.name}`"
                                                title="Delete"
                                                @click="remove(loc)"
                                            >
                                                <TIcon name="trash" :size="16" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TCard>
            </div>
        </div>

        <!-- Wide enough to put the fields beside the map they describe. At the
             default width the click-to-place map was a ~430px square wedged
             under the form, so placing a pin meant scrolling inside a dialog. -->
        <TModal v-model:show="showModal" max-width="3xl" @close="showModal = false">
            <template #title>{{ editing ? 'Edit location' : 'Add location' }}</template>

            <form @submit.prevent="save" class="grid gap-5 sm:grid-cols-2">
                <div class="space-y-4">
                    <TInput id="name" v-model="form.name" label="Name" :error="errors.name?.[0]" />

                    <div>
                        <label for="map-description" class="mb-1.5 block text-sm font-medium text-foreground">
                            Description <span class="font-normal text-foreground-muted">(optional)</span>
                        </label>
                        <textarea
                            id="map-description"
                            v-model="form.description"
                            rows="3"
                            class="block w-full rounded-lg border bg-surface px-3 py-2 text-sm text-foreground shadow-sm transition-colors placeholder:text-foreground-muted focus:border-primary focus:ring-2 focus:ring-primary/20"
                            placeholder="Shown when a visitor taps the pin"
                        />
                    </div>

                    <TSelect v-model="form.type" label="Type" :options="TYPE_OPTIONS" :error="errors.type?.[0]" />

                    <div class="grid grid-cols-2 gap-3">
                        <TInput
                            id="position_top"
                            v-model.number="form.position_top"
                            label="Top (%)"
                            type="number" min="0" max="100" step="0.1"
                            :error="errors.position_top?.[0]"
                        />
                        <TInput
                            id="position_left"
                            v-model.number="form.position_left"
                            label="Left (%)"
                            type="number" min="0" max="100" step="0.1"
                            :error="errors.position_left?.[0]"
                        />
                    </div>

                    <TSwitch v-model="form.is_active" label="Visible on the visitor map" />
                </div>

                <div>
                    <p class="mb-1.5 text-sm font-medium text-foreground">Click the map to place the pin</p>
                    <div
                        ref="mapRef"
                        class="relative aspect-[900/869] w-full cursor-crosshair overflow-hidden rounded-lg border"
                        @click="onMapClick"
                    >
                        <img src="/images/velaafinolhu.png" alt="Island map" class="absolute inset-0 h-full w-full object-cover" />
                        <div
                            class="pointer-events-none absolute -translate-x-1/2 -translate-y-full"
                            :style="{ top: `${form.position_top}%`, left: `${form.position_left}%` }"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="currentColor"
                                class="h-8 w-8 drop-shadow-lg"
                                :style="{ color: TYPE_COLOR[form.type] ?? TYPE_COLOR.general }"
                            >
                                <path d="M12 2C7.6 2 4 5.6 4 10c0 6 8 12 8 12s8-6 8-12c0-4.4-3.6-8-8-8zm0 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="showModal = false">Cancel</TButton>
                <TButton type="button" :loading="saving" @click="save">
                    {{ editing ? 'Save changes' : 'Add location' }}
                </TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
