<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import TSwitch from '@/Components/ui/TSwitch.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import { useHotelStore } from '@/stores/hotel';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { formatMoney } from '@/utils/format';

const hotelStore = useHotelStore();
const confirm = useConfirm();
const selectedHotelId = ref(null);
const showModal = ref(false);
const editingRoom = ref(null);
const errors = ref({});
const saving = ref(false);

const emptyForm = () => ({ room_number: '', type: 'single', price_per_night: null, max_guests: 1 });
const form = ref(emptyForm());

const ROOM_TYPE_OPTIONS = [
    { value: 'single', label: 'Single' },
    { value: 'double', label: 'Double' },
    { value: 'suite', label: 'Suite' },
];

const hotelOptions = computed(() =>
    hotelStore.hotels.map((hotel) => ({ value: hotel.id, label: hotel.name }))
);

const availableCount = computed(() => hotelStore.rooms.filter((r) => r.is_available).length);

const summary = computed(() => {
    const total = hotelStore.rooms.length;
    if (!total) return null;
    return `${total} room${total === 1 ? '' : 's'} · ${availableCount.value} available`;
});

onMounted(async () => {
    await hotelStore.fetchHotels({ all: true });
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
    // Only the editable fields. Spreading the whole room sent id, hotel_id and
    // timestamps back to the API on every save.
    form.value = {
        room_number: room.room_number,
        type: room.type,
        price_per_night: Number(room.price_per_night),
        max_guests: room.max_guests,
    };
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

const save = async () => {
    errors.value = {};
    saving.value = true;
    try {
        if (editingRoom.value) {
            await hotelStore.updateRoom(editingRoom.value.id, form.value);
        } else {
            await hotelStore.createRoom(selectedHotelId.value, form.value);
        }
        showToast(editingRoom.value ? 'Room updated.' : 'Room added.', 'success');
        closeModal();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        if (Object.keys(errors.value).length === 0) {
            showToast(e.response?.data?.message ?? 'Could not save this room.');
        }
    } finally {
        saving.value = false;
    }
};

const toggleAvailability = async (room) => {
    try {
        await hotelStore.updateRoom(room.id, { is_available: !room.is_available });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not change availability.');
    }
};

const remove = async (room) => {
    const ok = await confirm({
        title: `Delete room ${room.room_number}?`,
        message: 'The room is removed from this hotel. Existing bookings against it are not deleted.',
        confirmLabel: 'Delete room',
        danger: true,
    });
    if (!ok) return;

    try {
        await hotelStore.deleteRoom(room.id);
        showToast(`Room ${room.room_number} deleted.`, 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not delete this room.');
    }
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader compact title="Room Management" icon="bed" />
        </template>

        <div class="space-y-5">
            <StaffToolbar title="Rooms" :summary="summary">
                <template #actions>
                    <TButton :disabled="!selectedHotelId" @click="openAddModal">
                        <TIcon name="plus" :size="16" />
                        Add room
                    </TButton>
                </template>

                <div class="w-64">
                    <TSelect v-model="selectedHotelId" label="Hotel" :options="hotelOptions" />
                </div>
            </StaffToolbar>

            <TCard :padding="false">
                <div v-if="hotelStore.loading.rooms" class="p-8 text-center text-sm text-foreground-muted">
                    Loading rooms…
                </div>
                <div v-else-if="hotelStore.error.rooms" class="p-8 text-center text-sm text-danger">
                    {{ hotelStore.error.rooms }}
                </div>
                <div v-else-if="hotelStore.rooms.length === 0" class="p-4">
                    <TEmptyState
                        title="No rooms yet"
                        description="Add a room to this hotel to get started."
                        icon="bed"
                    >
                        <template #action>
                            <TButton :disabled="!selectedHotelId" @click="openAddModal">Add room</TButton>
                        </template>
                    </TEmptyState>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Room</th>
                                <th>Type</th>
                                <!-- Sleeps was settable in the form but shown nowhere,
                                     so the one field that decides which parties fit was
                                     invisible from the list. -->
                                <th class="num">Sleeps</th>
                                <th class="num">Price / night</th>
                                <th>Available</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="room in hotelStore.rooms" :key="room.id">
                                <td class="font-medium text-foreground">{{ room.room_number }}</td>
                                <td class="capitalize">{{ room.type }}</td>
                                <td class="num">{{ room.max_guests }}</td>
                                <td class="num text-foreground">{{ formatMoney(room.price_per_night) }}</td>
                                <td>
                                    <TSwitch
                                        :model-value="room.is_available"
                                        @update:model-value="toggleAvailability(room)"
                                    />
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-primary"
                                            :aria-label="`Edit room ${room.room_number}`"
                                            title="Edit"
                                            @click="openEditModal(room)"
                                        >
                                            <TIcon name="edit" :size="16" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                                            :aria-label="`Delete room ${room.room_number}`"
                                            title="Delete"
                                            @click="remove(room)"
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

        <TModal v-model:show="showModal" @close="closeModal">
            <template #title>{{ editingRoom ? 'Edit room' : 'Add room' }}</template>

            <form @submit.prevent="save" class="space-y-4">
                <TInput
                    id="room_number"
                    v-model="form.room_number"
                    label="Room number"
                    :error="errors.room_number?.[0]"
                />

                <TSelect
                    v-model="form.type"
                    label="Type"
                    :options="ROOM_TYPE_OPTIONS"
                    :error="errors.type?.[0]"
                />

                <div class="grid grid-cols-2 gap-4">
                    <TNumberInput
                        id="price_per_night"
                        v-model="form.price_per_night"
                        label="Price / night"
                        :min="0"
                        :step="0.01"
                        width="full"
                        :error="errors.price_per_night?.[0]"
                    />
                    <TNumberInput
                        id="max_guests"
                        v-model="form.max_guests"
                        label="Sleeps"
                        :min="1"
                        width="full"
                        :error="errors.max_guests?.[0]"
                    />
                </div>

                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="closeModal">Cancel</TButton>
                <TButton type="button" :loading="saving" @click="save">
                    {{ editingRoom ? 'Save changes' : 'Add room' }}
                </TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
