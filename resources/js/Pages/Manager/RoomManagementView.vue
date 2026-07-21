<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { useHotelStore } from '@/stores/hotel';

const hotelStore = useHotelStore();
const selectedHotelId = ref(null);
const showModal = ref(false);
const editingRoom = ref(null);
const errors = ref({});

const emptyForm = () => ({ room_number: '', type: 'single', price_per_night: '', max_guests: 1 });
const form = ref(emptyForm());

onMounted(async () => {
    await hotelStore.fetchHotels();
    if (hotelStore.hotels.length) selectedHotelId.value = hotelStore.hotels[0].id;
});

watch(selectedHotelId, (id) => {
    if (id) hotelStore.fetchRooms(id);
});

const openAddModal = () => {
    editingRoom.value = null;
    form.value = emptyForm();
    errors.value = {};
    showModal.value = true;
};

const openEditModal = (room) => {
    editingRoom.value = room;
    form.value = { ...room };
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const save = async () => {
    errors.value = {};
    try {
        if (editingRoom.value) {
            await hotelStore.updateRoom(editingRoom.value.id, form.value);
        } else {
            await hotelStore.createRoom(selectedHotelId.value, form.value);
        }
        closeModal();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
    }
};

const toggleAvailability = (room) => {
    hotelStore.updateRoom(room.id, { is_available: !room.is_available });
};

const remove = (room) => {
    if (confirm(`Delete room ${room.room_number}?`)) {
        hotelStore.deleteRoom(room.id);
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Room Management
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Hotel</label>
                        <select v-model="selectedHotelId" class="mt-1 rounded-md border-gray-300 shadow-sm">
                            <option v-for="hotel in hotelStore.hotels" :key="hotel.id" :value="hotel.id">
                                {{ hotel.name }}
                            </option>
                        </select>
                    </div>
                    <PrimaryButton :disabled="!selectedHotelId" @click="openAddModal">Add Room</PrimaryButton>
                </div>

                <div class="rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-gray-500">
                                <th class="p-4">Room #</th>
                                <th class="p-4">Type</th>
                                <th class="p-4">Price / night</th>
                                <th class="p-4">Available</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="room in hotelStore.rooms" :key="room.id">
                                <td class="p-4">{{ room.room_number }}</td>
                                <td class="p-4 capitalize">{{ room.type }}</td>
                                <td class="p-4">${{ room.price_per_night }}</td>
                                <td class="p-4">
                                    <button
                                        @click="toggleAvailability(room)"
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="room.is_available
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ room.is_available ? 'Available' : 'Unavailable' }}
                                    </button>
                                </td>
                                <td class="p-4 space-x-2">
                                    <button @click="openEditModal(room)" class="text-sm text-indigo-600 hover:underline">
                                        Edit
                                    </button>
                                    <button @click="remove(room)" class="text-sm text-red-600 hover:underline">
                                        Delete
                                    </button>
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
                    {{ editingRoom ? 'Edit Room' : 'Add Room' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="room_number" value="Room Number" />
                    <TextInput id="room_number" v-model="form.room_number" class="mt-1 block w-full" />
                    <InputError :message="errors.room_number?.[0]" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="type" value="Type" />
                    <select id="type" v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="single">Single</option>
                        <option value="double">Double</option>
                        <option value="suite">Suite</option>
                    </select>
                    <InputError :message="errors.type?.[0]" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="price_per_night" value="Price per Night" />
                    <TextInput id="price_per_night" type="number" step="0.01" v-model="form.price_per_night" class="mt-1 block w-full" />
                    <InputError :message="errors.price_per_night?.[0]" class="mt-2" />
                </div>

                <div class="mt-4">
                    <InputLabel for="max_guests" value="Max Guests" />
                    <TextInput id="max_guests" type="number" min="1" v-model="form.max_guests" class="mt-1 block w-full" />
                    <InputError :message="errors.max_guests?.[0]" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="closeModal">Cancel</SecondaryButton>
                    <PrimaryButton type="submit">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
