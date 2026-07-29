<script setup>
import { onMounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TCard from '@/Components/ui/TCard.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import { useThemeParkStore } from '@/stores/themepark';

const themeParkStore = useThemeParkStore();
const showModal = ref(false);
const editingEvent = ref(null);
const errors = ref({});

const emptyForm = () => ({
    name: '', description: '', type: 'ride', location: '',
    duration_minutes: 30, capacity_per_slot: 20, price_per_ticket: '', image_url: '',
});
const form = ref(emptyForm());

onMounted(() => themeParkStore.fetchEvents());

const openAddModal = () => {
    editingEvent.value = null;
    form.value = emptyForm();
    errors.value = {};
    showModal.value = true;
};

const openEditModal = (event) => {
    editingEvent.value = event;
    form.value = { ...event };
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => (showModal.value = false);

const save = async () => {
    errors.value = {};
    try {
        if (editingEvent.value) {
            await themeParkStore.updateEvent(editingEvent.value.id, form.value);
        } else {
            await themeParkStore.createEvent(form.value);
        }
        closeModal();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
    }
};

const toggleActive = (event) => {
    themeParkStore.updateEvent(event.id, { is_active: !event.is_active });
};

const remove = (event) => {
    if (confirm(`Delete ${event.name}?`)) {
        themeParkStore.deleteEvent(event.id);
    }
};

const typeOptions = [
    { value: 'ride', label: 'Ride' },
    { value: 'show', label: 'Show' },
    { value: 'beach_event', label: 'Beach Event' },
];
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Event Management" icon="sparkle">
                <template #actions>
                    <TButton size="sm" @click="openAddModal">
                        <TIcon name="plus" :size="16" />
                        Add Event
                    </TButton>
                </template>
            </TPageHeader>
        </template>

        <div class="max-w-5xl">
            <TCard icon="sparkle" title="Events" :padding="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[rgb(var(--color-border))] text-sm">
                        <thead>
                            <tr class="text-left text-foreground-muted">
                                <th class="p-4">Name</th>
                                <th class="p-4">Type</th>
                                <th class="p-4">Location</th>
                                <th class="p-4">Capacity</th>
                                <th class="p-4">Active</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgb(var(--color-border))]">
                            <tr v-for="event in themeParkStore.events" :key="event.id">
                                <td class="p-4">{{ event.name }}</td>
                                <td class="p-4 capitalize">{{ event.type.replace('_', ' ') }}</td>
                                <td class="p-4">{{ event.location }}</td>
                                <td class="p-4">{{ event.capacity_per_slot }}</td>
                                <td class="p-4">
                                    <button @click="toggleActive(event)">
                                        <TBadge :variant="event.is_active ? 'success' : 'neutral'">
                                            {{ event.is_active ? 'Active' : 'Inactive' }}
                                        </TBadge>
                                    </button>
                                </td>
                                <td class="p-4 space-x-2">
                                    <button @click="openEditModal(event)" class="text-sm text-primary hover:underline">Edit</button>
                                    <button @click="remove(event)" class="text-sm text-danger hover:underline">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>
        </div>

        <TModal v-model:show="showModal">
            <template #title>{{ editingEvent ? 'Edit Event' : 'Add Event' }}</template>

            <form id="event-form" @submit.prevent="save" class="space-y-4">
                <TInput v-model="form.name" label="Name" :error="errors.name?.[0]" />
                <TInput v-model="form.description" label="Description" />
                <TSelect v-model="form.type" label="Type" :error="errors.type?.[0]" :options="typeOptions" />
                <TInput v-model="form.location" label="Location" :error="errors.location?.[0]" />
                <div class="flex gap-4">
                    <div class="flex-1">
                        <TInput v-model="form.duration_minutes" label="Duration (min)" type="number" min="1" :error="errors.duration_minutes?.[0]" />
                    </div>
                    <div class="flex-1">
                        <TInput v-model="form.capacity_per_slot" label="Capacity" type="number" min="1" :error="errors.capacity_per_slot?.[0]" />
                    </div>
                </div>
                <TInput v-model="form.price_per_ticket" label="Price per Ticket" type="number" step="0.01" :error="errors.price_per_ticket?.[0]" />
                <TInput v-model="form.image_url" label="Image URL" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="closeModal">Cancel</TButton>
                <TButton type="submit" form="event-form">Save</TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
