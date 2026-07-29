<script setup>
import { onMounted, ref, watch } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import { useHotelStore } from '@/stores/hotel';

const hotelStore = useHotelStore();
const selectedHotelId = ref(null);
const showModal = ref(false);
const editingRoom = ref(null);
const errors = ref({});

const emptyForm = () => ({ room_number: '', type: 'single', price_per_night: '', max_guests: 1 });
const form = ref(emptyForm());

const ROOM_TYPE_OPTIONS = [
    { value: 'single', label: 'Single' },
    { value: 'double', label: 'Double' },
    { value: 'suite', label: 'Suite' },
];

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
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Room Management" icon="bed" />
        </template>

        <div class="max-w-5xl space-y-6">
            <TCard>
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <label class="block text-sm font-medium text-foreground-secondary">Hotel</label>
                        <select
                            v-model="selectedHotelId"
                            class="mt-1 rounded-lg border bg-surface text-sm text-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/20"
                        >
                            <option v-for="hotel in hotelStore.hotels" :key="hotel.id" :value="hotel.id">
                                {{ hotel.name }}
                            </option>
                        </select>
                    </div>
                    <TButton :disabled="!selectedHotelId" @click="openAddModal">
                        <TIcon name="plus" :size="16" />
                        Add Room
                    </TButton>
                </div>
            </TCard>

            <TCard icon="bed" title="Rooms" :padding="false">
                <div v-if="hotelStore.rooms.length === 0" class="p-4">
                    <TEmptyState
                        title="No rooms yet"
                        description="Add a room to this hotel to get started."
                        icon="bed"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-[rgb(var(--color-border))] text-sm">
                        <thead>
                            <tr class="text-left text-foreground-muted">
                                <th class="p-4">Room #</th>
                                <th class="p-4">Type</th>
                                <th class="p-4">Price / night</th>
                                <th class="p-4">Available</th>
                                <th class="p-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgb(var(--color-border))] text-foreground-secondary">
                            <tr v-for="room in hotelStore.rooms" :key="room.id">
                                <td class="p-4 text-foreground">{{ room.room_number }}</td>
                                <td class="p-4 capitalize">{{ room.type }}</td>
                                <td class="p-4">${{ room.price_per_night }}</td>
                                <td class="p-4">
                                    <button type="button" @click="toggleAvailability(room)">
                                        <TBadge :variant="room.is_available ? 'success' : 'neutral'">
                                            {{ room.is_available ? 'Available' : 'Unavailable' }}
                                        </TBadge>
                                    </button>
                                </td>
                                <td class="p-4 space-x-2">
                                    <button @click="openEditModal(room)" class="text-sm text-primary hover:underline">
                                        Edit
                                    </button>
                                    <button @click="remove(room)" class="text-sm text-danger hover:underline">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TCard>
        </div>

        <TModal v-model:show="showModal" @close="closeModal">
            <template #title>{{ editingRoom ? 'Edit Room' : 'Add Room' }}</template>

            <form @submit.prevent="save" class="space-y-4">
                <TInput
                    id="room_number"
                    v-model="form.room_number"
                    label="Room Number"
                    :error="errors.room_number?.[0]"
                />

                <TSelect
                    v-model="form.type"
                    label="Type"
                    :options="ROOM_TYPE_OPTIONS"
                    :error="errors.type?.[0]"
                />

                <TInput
                    id="price_per_night"
                    v-model="form.price_per_night"
                    label="Price per Night"
                    type="number"
                    step="0.01"
                    :error="errors.price_per_night?.[0]"
                />

                <TInput
                    id="max_guests"
                    v-model="form.max_guests"
                    label="Max Guests"
                    type="number"
                    min="1"
                    :error="errors.max_guests?.[0]"
                />

                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="closeModal">Cancel</TButton>
                <TButton type="button" @click="save">Save</TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
