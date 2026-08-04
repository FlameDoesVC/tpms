<script setup>
import { computed, onMounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TInput from '@/Components/ui/TInput.vue';
import TImageUpload from '@/Components/ui/TImageUpload.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import TSwitch from '@/Components/ui/TSwitch.vue';
import TCard from '@/Components/ui/TCard.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import { useThemeParkStore } from '@/stores/themepark';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { formatMoney } from '@/utils/format';

const themeParkStore = useThemeParkStore();
const confirm = useConfirm();
const showModal = ref(false);
const editingEvent = ref(null);
const errors = ref({});
const saving = ref(false);

const typeOptions = [
    { value: 'ride', label: 'Ride' },
    { value: 'show', label: 'Show' },
    { value: 'beach_event', label: 'Beach Event' },
];
const TYPE_LABELS = Object.fromEntries(typeOptions.map((o) => [o.value, o.label]));

const emptyForm = () => ({
    name: '', description: '', type: 'ride', location: '',
    duration_minutes: 30, capacity_per_slot: 20, price_per_ticket: null,
});
const form = ref(emptyForm());
const imageFile = ref(null);
const imageRemoved = ref(false);
const currentImageUrl = ref(null);

const search = ref('');
const typeFilter = ref('');

const typeFilterOptions = computed(() => {
    const present = new Set(themeParkStore.events.map((e) => e.type));
    return [{ value: '', label: 'All types' }, ...typeOptions.filter((o) => present.has(o.value))];
});

const visibleEvents = computed(() => {
    let list = themeParkStore.events;
    const query = search.value.trim().toLowerCase();
    if (query) {
        list = list.filter((e) => `${e.name ?? ''} ${e.location ?? ''}`.toLowerCase().includes(query));
    }
    if (typeFilter.value) {
        list = list.filter((e) => e.type === typeFilter.value);
    }
    return list;
});

const summary = computed(() => {
    const total = themeParkStore.events.length;
    if (!total) return null;
    const shown = visibleEvents.value.length;
    const active = themeParkStore.events.filter((e) => e.is_active).length;
    return shown === total
        ? `${total} event${total === 1 ? '' : 's'} · ${active} active`
        : `${shown} of ${total} events`;
});

// Management scope: inactive events have to appear here, or the is_active
// toggle below is one-way.
onMounted(() => themeParkStore.fetchEvents({ all: true }));

const openAddModal = () => {
    editingEvent.value = null;
    form.value = emptyForm();
    imageFile.value = null;
    imageRemoved.value = false;
    currentImageUrl.value = null;
    errors.value = {};
    showModal.value = true;
};

const openEditModal = (event) => {
    editingEvent.value = event;
    // Editable fields only. Spreading the event sent id, timestamps and any
    // eager-loaded relations back to the API on every save.
    form.value = {
        name: event.name,
        description: event.description ?? '',
        type: event.type,
        location: event.location ?? '',
        duration_minutes: event.duration_minutes,
        capacity_per_slot: event.capacity_per_slot,
        price_per_ticket: Number(event.price_per_ticket),
    };
    imageFile.value = null;
    imageRemoved.value = false;
    currentImageUrl.value = event.image_url ?? null;
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => (showModal.value = false);

// Only reaches for FormData when there's actually a file to send or an
// existing image to clear - plain JSON keeps working for every other edit
// (including the is_active toggle, which calls updateEvent separately).
const buildPayload = () => {
    if (!imageFile.value && !imageRemoved.value) {
        return { ...form.value };
    }
    const payload = new FormData();
    Object.entries(form.value).forEach(([key, value]) => {
        if (value !== null && value !== undefined) payload.append(key, value);
    });
    if (imageFile.value) {
        payload.append('image', imageFile.value);
    } else if (imageRemoved.value) {
        payload.append('remove_image', '1');
    }
    return payload;
};

const save = async () => {
    errors.value = {};
    saving.value = true;
    try {
        const payload = buildPayload();
        if (editingEvent.value) {
            await themeParkStore.updateEvent(editingEvent.value.id, payload);
        } else {
            await themeParkStore.createEvent(payload);
        }
        showToast(editingEvent.value ? 'Event updated.' : 'Event created.', 'success');
        closeModal();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        if (Object.keys(errors.value).length === 0) {
            showToast(e.response?.data?.message ?? 'Could not save this event.');
        }
    } finally {
        saving.value = false;
    }
};

const toggleActive = async (event) => {
    try {
        await themeParkStore.updateEvent(event.id, { is_active: !event.is_active });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not change this event.');
    }
};

const remove = async (event) => {
    const ok = await confirm({
        title: `Delete ${event.name}?`,
        message: 'The event and its time slots are removed. Turn it inactive instead if you only want it off sale.',
        confirmLabel: 'Delete event',
        danger: true,
    });
    if (!ok) return;

    try {
        await themeParkStore.deleteEvent(event.id);
        showToast(`${event.name} deleted.`, 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not delete this event.');
    }
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Event Management" icon="sparkle" />
        </template>

        <div class="space-y-5">
            <StaffToolbar title="Events" :summary="summary">
                <template #actions>
                    <TButton @click="openAddModal">
                        <TIcon name="plus" :size="16" />
                        Add event
                    </TButton>
                </template>

                <TInput
                    v-model="search"
                    type="search"
                    label="Search"
                    placeholder="Event name or location"
                    class="min-w-[16rem] flex-1"
                >
                    <template #prefix><TIcon name="search" :size="16" /></template>
                </TInput>
                <div class="w-48">
                    <TSelect v-model="typeFilter" label="Type" :options="typeFilterOptions" />
                </div>
            </StaffToolbar>

            <TCard :padding="false">
                <!-- Neither a loading nor an empty state existed: before the first
                     fetch resolved the page showed a table with a header and no
                     body, which reads as "there are no events". -->
                <div v-if="themeParkStore.loading.events" class="p-8 text-center text-sm text-foreground-muted">
                    Loading events…
                </div>
                <div v-else-if="themeParkStore.events.length === 0" class="p-4">
                    <TEmptyState
                        title="No events yet"
                        description="Add a ride, show or beach event before scheduling time slots for it."
                        icon="sparkle"
                    >
                        <template #action>
                            <TButton @click="openAddModal">Add event</TButton>
                        </template>
                    </TEmptyState>
                </div>
                <div v-else-if="visibleEvents.length === 0" class="p-4">
                    <TEmptyState title="No events match" description="Try a different name, or clear the type filter." icon="search" />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th class="num">Duration</th>
                                <th class="num">Capacity</th>
                                <!-- Price was settable in the form but shown nowhere. -->
                                <th class="num">Price</th>
                                <th>Active</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="event in visibleEvents" :key="event.id">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-12 shrink-0 place-items-center overflow-hidden rounded bg-surface-hover text-foreground-muted">
                                            <img
                                                v-if="event.image_url"
                                                :src="event.image_url"
                                                :alt="event.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <TIcon v-else name="sparkle" :size="14" />
                                        </span>
                                        <span class="font-medium text-foreground">{{ event.name }}</span>
                                    </div>
                                </td>
                                <td>
                                    <TBadge variant="primary">{{ TYPE_LABELS[event.type] ?? event.type }}</TBadge>
                                </td>
                                <td>{{ event.location }}</td>
                                <td class="num whitespace-nowrap">{{ event.duration_minutes }} min</td>
                                <td class="num">{{ event.capacity_per_slot }}</td>
                                <td class="num text-foreground">{{ formatMoney(event.price_per_ticket) }}</td>
                                <td>
                                    <TSwitch
                                        :model-value="event.is_active"
                                        @update:model-value="toggleActive(event)"
                                    />
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-primary"
                                            :aria-label="`Edit ${event.name}`"
                                            title="Edit"
                                            @click="openEditModal(event)"
                                        >
                                            <TIcon name="edit" :size="16" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                                            :aria-label="`Delete ${event.name}`"
                                            title="Delete"
                                            @click="remove(event)"
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

        <TModal v-model:show="showModal" max-width="xl">
            <template #title>{{ editingEvent ? 'Edit event' : 'Add event' }}</template>

            <form id="event-form" @submit.prevent="save" class="space-y-4">
                <TInput v-model="form.name" label="Name" :error="errors.name?.[0]" />
                <TInput v-model="form.description" label="Description" placeholder="Shown on the event card" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <TSelect v-model="form.type" label="Type" :error="errors.type?.[0]" :options="typeOptions" />
                    <TInput v-model="form.location" label="Location" :error="errors.location?.[0]" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <TNumberInput
                        v-model="form.duration_minutes"
                        label="Duration (min)"
                        :min="1"
                        width="full"
                        :error="errors.duration_minutes?.[0]"
                    />
                    <TNumberInput
                        v-model="form.capacity_per_slot"
                        label="Capacity / slot"
                        :min="1"
                        width="full"
                        :error="errors.capacity_per_slot?.[0]"
                    />
                    <!-- `:step` bound, not `step="0.01"` — the string form failed
                         TNumberInput's Number prop type and logged a warning. -->
                    <TNumberInput
                        v-model="form.price_per_ticket"
                        label="Price / ticket"
                        :min="0"
                        :step="0.01"
                        width="full"
                        :error="errors.price_per_ticket?.[0]"
                    />
                </div>

                <TImageUpload
                    v-model:file="imageFile"
                    v-model:removed="imageRemoved"
                    :current-url="currentImageUrl"
                    label="Image"
                    :error="errors.image?.[0]"
                />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="closeModal">Cancel</TButton>
                <TButton type="submit" form="event-form" :loading="saving">
                    {{ editingEvent ? 'Save changes' : 'Create event' }}
                </TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
