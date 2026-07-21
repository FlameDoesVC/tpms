<script setup>
import { onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
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
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Event Management
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex justify-end">
                    <PrimaryButton @click="openAddModal">Add Event</PrimaryButton>
                </div>

                <div class="rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="p-4">Name</th>
                                <th class="p-4">Type</th>
                                <th class="p-4">Location</th>
                                <th class="p-4">Capacity</th>
                                <th class="p-4">Active</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="event in themeParkStore.events" :key="event.id">
                                <td class="p-4">{{ event.name }}</td>
                                <td class="p-4 capitalize">{{ event.type.replace('_', ' ') }}</td>
                                <td class="p-4">{{ event.location }}</td>
                                <td class="p-4">{{ event.capacity_per_slot }}</td>
                                <td class="p-4">
                                    <button
                                        @click="toggleActive(event)"
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="event.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ event.is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </td>
                                <td class="p-4 space-x-2">
                                    <button @click="openEditModal(event)" class="text-sm text-indigo-600 hover:underline">Edit</button>
                                    <button @click="remove(event)" class="text-sm text-red-600 hover:underline">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="showModal" @close="closeModal">
            <form @submit.prevent="save" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editingEvent ? 'Edit Event' : 'Add Event' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" />
                    <InputError :message="errors.name?.[0]" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="description" value="Description" />
                    <TextInput id="description" v-model="form.description" class="mt-1 block w-full" />
                </div>

                <div class="mt-4">
                    <InputLabel for="type" value="Type" />
                    <select id="type" v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="ride">Ride</option>
                        <option value="show">Show</option>
                        <option value="beach_event">Beach Event</option>
                    </select>
                    <InputError :message="errors.type?.[0]" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="location" value="Location" />
                    <TextInput id="location" v-model="form.location" class="mt-1 block w-full" />
                    <InputError :message="errors.location?.[0]" class="mt-2" />
                </div>

                <div class="mt-4 flex gap-4">
                    <div class="flex-1">
                        <InputLabel for="duration_minutes" value="Duration (min)" />
                        <TextInput id="duration_minutes" type="number" min="1" v-model="form.duration_minutes" class="mt-1 block w-full" />
                        <InputError :message="errors.duration_minutes?.[0]" class="mt-2" />
                    </div>
                    <div class="flex-1">
                        <InputLabel for="capacity_per_slot" value="Capacity" />
                        <TextInput id="capacity_per_slot" type="number" min="1" v-model="form.capacity_per_slot" class="mt-1 block w-full" />
                        <InputError :message="errors.capacity_per_slot?.[0]" class="mt-2" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="price_per_ticket" value="Price per Ticket" />
                    <TextInput id="price_per_ticket" type="number" step="0.01" v-model="form.price_per_ticket" class="mt-1 block w-full" />
                    <InputError :message="errors.price_per_ticket?.[0]" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="image_url" value="Image URL" />
                    <TextInput id="image_url" v-model="form.image_url" class="mt-1 block w-full" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                    <PrimaryButton type="submit">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
