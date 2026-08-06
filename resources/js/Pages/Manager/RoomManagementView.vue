<script setup>
/**
 * Room types and the physical rooms behind them.
 *
 * Two levels, because that is what the schema now is: a room type carries the
 * price, capacity, description and photographs a guest chooses from, and rooms
 * are the numbered inventory it is fulfilled out of. This page used to be one
 * flat table where every room repeated its own price and capacity, and a "room
 * type" existed only as whatever those columns happened to have in common.
 */
import { computed, onMounted, reactive, ref, watch } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import FacilityList from '@/Components/FacilityList.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import TButton from '@/Components/ui/TButton.vue';
import TCard from '@/Components/ui/TCard.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import TGalleryUpload from '@/Components/ui/TGalleryUpload.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TImageUpload from '@/Components/ui/TImageUpload.vue';
import TInput from '@/Components/ui/TInput.vue';
import TModal from '@/Components/ui/TModal.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TSelect from '@/Components/ui/TSelect.vue';
import TSwitch from '@/Components/ui/TSwitch.vue';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';
import { ROOM_AMENITIES } from '@/utils/facilities';
import { formatMoney } from '@/utils/format';
import { useHotelStore } from '@/stores/hotel';

const hotelStore = useHotelStore();
const confirm = useConfirm();

const selectedHotelId = ref(null);
const expanded = reactive({});
const loading = ref(false);

const hotelOptions = computed(() =>
    hotelStore.hotels.map((hotel) => ({ value: hotel.id, label: hotel.name }))
);

const roomTypes = computed(() => hotelStore.roomTypes);
const roomsOfType = (roomTypeId) => hotelStore.rooms.filter((room) => room.room_type_id === roomTypeId);

const summary = computed(() => {
    const types = roomTypes.value.length;
    if (!types) return null;
    const rooms = hotelStore.rooms.length;
    return `${types} room type${types === 1 ? '' : 's'} · ${rooms} room${rooms === 1 ? '' : 's'}`;
});

const load = async (hotelId) => {
    if (!hotelId) return;
    loading.value = true;
    try {
        await Promise.all([
            hotelStore.fetchManagerRoomTypes(hotelId),
            hotelStore.fetchRooms(hotelId),
        ]);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await hotelStore.fetchHotels({ all: true });
    if (hotelStore.hotels.length) selectedHotelId.value = hotelStore.hotels[0].id;
});

watch(selectedHotelId, (id) => load(id));

/* ------------------------------ room types ------------------------------ */

const showTypeModal = ref(false);
const editingType = ref(null);
const typeErrors = ref({});
const savingType = ref(false);

const emptyTypeForm = () => ({
    name: '',
    description: '',
    price_per_night: null,
    max_guests: 2,
    amenities: [],
    is_active: true,
});
const typeForm = ref(emptyTypeForm());
const coverFile = ref(null);
const coverRemoved = ref(false);
const currentCoverUrl = ref(null);
const galleryFiles = ref([]);
const galleryRemovedIds = ref([]);
const currentGallery = ref([]);

const toggleAmenity = (slug) => {
    typeForm.value.amenities = typeForm.value.amenities.includes(slug)
        ? typeForm.value.amenities.filter((s) => s !== slug)
        : [...typeForm.value.amenities, slug];
};

const resetTypeMedia = (roomType = null) => {
    coverFile.value = null;
    coverRemoved.value = false;
    currentCoverUrl.value = roomType?.image_url ?? null;
    galleryFiles.value = [];
    galleryRemovedIds.value = [];
    currentGallery.value = roomType?.gallery ?? [];
};

const openAddType = () => {
    editingType.value = null;
    typeForm.value = emptyTypeForm();
    resetTypeMedia();
    typeErrors.value = {};
    showTypeModal.value = true;
};

const openEditType = (roomType) => {
    editingType.value = roomType;
    typeForm.value = {
        name: roomType.name,
        description: roomType.description ?? '',
        price_per_night: Number(roomType.price_per_night),
        max_guests: roomType.max_guests,
        amenities: [...(roomType.amenities ?? [])],
        is_active: roomType.is_active,
    };
    resetTypeMedia(roomType);
    typeErrors.value = {};
    showTypeModal.value = true;
};

// Plain JSON unless there is actually a file to send or a cover to clear.
const buildTypePayload = () => {
    const fields = { ...typeForm.value };

    if (!coverFile.value && !coverRemoved.value) return fields;

    const payload = new FormData();
    Object.entries(fields).forEach(([key, value]) => {
        if (value === null || value === undefined) return;
        // Arrays need the [] suffix or only the last entry survives.
        if (Array.isArray(value)) {
            value.forEach((entry) => payload.append(`${key}[]`, entry));
            return;
        }
        // Laravel's `boolean` rule rejects the "true"/"false" strings FormData
        // would otherwise coerce a JS boolean into.
        payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value);
    });
    if (coverFile.value) payload.append('image', coverFile.value);
    else if (coverRemoved.value) payload.append('remove_image', '1');

    return payload;
};

const saveType = async () => {
    typeErrors.value = {};
    savingType.value = true;
    try {
        const payload = buildTypePayload();
        const saved = editingType.value
            ? await hotelStore.updateRoomType(editingType.value.id, payload)
            : await hotelStore.createRoomType(selectedHotelId.value, payload);

        // Gallery work happens after the type exists - a create has no id to
        // upload against until now.
        if (galleryFiles.value.length) {
            await hotelStore.uploadRoomTypeGallery(saved.id, galleryFiles.value);
        }
        for (const mediaId of galleryRemovedIds.value) {
            await hotelStore.deleteRoomTypeGalleryImage(saved.id, mediaId);
        }

        await load(selectedHotelId.value);
        showToast(editingType.value ? 'Room type updated.' : 'Room type created.', 'success');
        showTypeModal.value = false;
    } catch (e) {
        typeErrors.value = e.response?.data?.errors ?? {};
        if (Object.keys(typeErrors.value).length === 0) {
            showToast(e.response?.data?.message ?? 'Could not save this room type.');
        }
    } finally {
        savingType.value = false;
    }
};

const removeType = async (roomType) => {
    const ok = await confirm({
        title: `Delete ${roomType.name}?`,
        message: 'Only an empty room type can be deleted - move or delete its rooms first.',
        confirmLabel: 'Delete room type',
        danger: true,
    });
    if (!ok) return;

    try {
        await hotelStore.deleteRoomType(roomType.id);
        showToast(`${roomType.name} deleted.`, 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not delete this room type.');
    }
};

/* --------------------------------- rooms -------------------------------- */

const showRoomModal = ref(false);
const editingRoom = ref(null);
const roomTypeForNewRoom = ref(null);
const roomErrors = ref({});
const savingRoom = ref(false);
const roomForm = ref({ room_number: '' });

const openAddRoom = (roomType) => {
    editingRoom.value = null;
    roomTypeForNewRoom.value = roomType;
    roomForm.value = { room_number: '' };
    roomErrors.value = {};
    showRoomModal.value = true;
};

const openEditRoom = (room) => {
    editingRoom.value = room;
    roomTypeForNewRoom.value = null;
    roomForm.value = { room_number: room.room_number };
    roomErrors.value = {};
    showRoomModal.value = true;
};

const saveRoom = async () => {
    roomErrors.value = {};
    savingRoom.value = true;
    try {
        if (editingRoom.value) {
            await hotelStore.updateRoom(editingRoom.value.id, roomForm.value);
        } else {
            await hotelStore.createRoom(selectedHotelId.value, {
                ...roomForm.value,
                room_type_id: roomTypeForNewRoom.value.id,
            });
        }
        await load(selectedHotelId.value);
        showToast(editingRoom.value ? 'Room updated.' : 'Room added.', 'success');
        showRoomModal.value = false;
    } catch (e) {
        roomErrors.value = e.response?.data?.errors ?? {};
        if (Object.keys(roomErrors.value).length === 0) {
            showToast(e.response?.data?.message ?? 'Could not save this room.');
        }
    } finally {
        savingRoom.value = false;
    }
};

const toggleAvailability = async (room) => {
    try {
        await hotelStore.updateRoom(room.id, { is_available: !room.is_available });
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not change availability.');
    }
};

const removeRoom = async (room) => {
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
            <TPageHeader compact title="Rooms & Room Types" icon="bed" />
        </template>

        <div class="space-y-5">
            <StaffToolbar title="Room types" :summary="summary">
                <template #actions>
                    <TButton :disabled="!selectedHotelId" @click="openAddType">
                        <TIcon name="plus" :size="16" />
                        Add room type
                    </TButton>
                </template>

                <div class="w-64">
                    <TSelect v-model="selectedHotelId" label="Hotel" :options="hotelOptions" />
                </div>
            </StaffToolbar>

            <div v-if="loading" class="space-y-3">
                <div v-for="n in 2" :key="n" class="h-28 animate-pulse rounded-xl border bg-surface-hover" />
            </div>

            <TCard v-else-if="roomTypes.length === 0" :padding="false">
                <div class="p-4">
                    <TEmptyState
                        title="No room types yet"
                        description="A room type is what guests choose - its price, capacity and photos. Add one, then put rooms in it."
                        icon="bed"
                    >
                        <template #action>
                            <TButton :disabled="!selectedHotelId" @click="openAddType">Add room type</TButton>
                        </template>
                    </TEmptyState>
                </div>
            </TCard>

            <TCard v-for="roomType in roomTypes" v-else :key="roomType.id" :padding="false">
                <div class="flex flex-wrap items-start gap-4 p-4">
                    <span class="grid h-16 w-24 shrink-0 place-items-center overflow-hidden rounded-lg border bg-surface-hover text-foreground-muted">
                        <img v-if="roomType.image_url" :src="roomType.image_url" alt="" class="h-full w-full object-cover" />
                        <TIcon v-else name="image" :size="20" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-foreground">{{ roomType.name }}</h3>
                            <span v-if="!roomType.is_active" class="rounded bg-surface-sunken px-1.5 py-0.5 text-xs text-foreground-muted">
                                Inactive
                            </span>
                        </div>
                        <p class="mt-0.5 text-sm text-foreground-secondary">
                            {{ formatMoney(roomType.price_per_night) }} / night · sleeps {{ roomType.max_guests }}
                            · {{ roomsOfType(roomType.id).length }} room{{ roomsOfType(roomType.id).length === 1 ? '' : 's' }}
                            · {{ (roomType.gallery?.length ?? 0) }} photo{{ (roomType.gallery?.length ?? 0) === 1 ? '' : 's' }}
                        </p>
                        <FacilityList
                            v-if="roomType.amenities?.length"
                            class="mt-2"
                            :items="roomType.amenities"
                            variant="chips"
                            :limit="5"
                        />
                    </div>

                    <div class="flex items-center gap-1">
                        <TButton size="sm" variant="secondary" @click="openAddRoom(roomType)">Add room</TButton>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-primary"
                            :aria-label="`Edit ${roomType.name}`"
                            title="Edit"
                            @click="openEditType(roomType)"
                        >
                            <TIcon name="edit" :size="16" />
                        </button>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                            :aria-label="`Delete ${roomType.name}`"
                            title="Delete"
                            @click="removeType(roomType)"
                        >
                            <TIcon name="trash" :size="16" />
                        </button>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-foreground"
                            :aria-label="`Show rooms in ${roomType.name}`"
                            @click="expanded[roomType.id] = !expanded[roomType.id]"
                        >
                            <TIcon
                                name="chevronDown"
                                :size="16"
                                class="transition-transform"
                                :class="expanded[roomType.id] ? 'rotate-180' : ''"
                            />
                        </button>
                    </div>
                </div>

                <div v-if="expanded[roomType.id]" class="border-t">
                    <div v-if="roomsOfType(roomType.id).length === 0" class="p-4 text-sm text-foreground-muted">
                        No rooms in this type yet - guests cannot book it until there is at least one.
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Available</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="room in roomsOfType(roomType.id)" :key="room.id">
                                    <td class="font-medium text-foreground">{{ room.room_number }}</td>
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
                                                @click="openEditRoom(room)"
                                            >
                                                <TIcon name="edit" :size="16" />
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                                                :aria-label="`Delete room ${room.room_number}`"
                                                title="Delete"
                                                @click="removeRoom(room)"
                                            >
                                                <TIcon name="trash" :size="16" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </TCard>
        </div>

        <TModal v-model:show="showTypeModal" @close="showTypeModal = false">
            <template #title>{{ editingType ? 'Edit room type' : 'Add room type' }}</template>

            <form class="space-y-4" @submit.prevent="saveType">
                <!-- Free text: the old single/double/suite enum could not
                     describe what a resort actually sells. -->
                <TInput
                    id="room_type_name"
                    v-model="typeForm.name"
                    label="Name"
                    placeholder="e.g. Ocean Double"
                    :error="typeErrors.name?.[0]"
                />
                <TInput
                    id="room_type_description"
                    v-model="typeForm.description"
                    label="Description"
                    placeholder="What the room is like - shown on the hotel page."
                    :error="typeErrors.description?.[0]"
                />

                <div class="grid grid-cols-2 gap-4">
                    <TNumberInput
                        v-model="typeForm.price_per_night"
                        label="Price / night"
                        :min="0"
                        :step="0.01"
                        width="full"
                        :error="typeErrors.price_per_night?.[0]"
                    />
                    <TNumberInput
                        v-model="typeForm.max_guests"
                        label="Sleeps"
                        :min="1"
                        width="full"
                        :error="typeErrors.max_guests?.[0]"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-foreground">Amenities</label>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-2 rounded-lg border p-3 sm:grid-cols-3">
                        <label
                            v-for="amenity in ROOM_AMENITIES"
                            :key="amenity.slug"
                            class="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="h-4 w-4 shrink-0 rounded border-strong bg-surface text-primary focus:ring-2 focus:ring-primary/20"
                                :checked="typeForm.amenities.includes(amenity.slug)"
                                @change="toggleAmenity(amenity.slug)"
                            />
                            <span class="text-foreground">{{ amenity.label }}</span>
                        </label>
                    </div>
                    <p v-if="typeErrors['amenities.0']?.[0]" class="mt-1.5 text-sm text-danger">
                        {{ typeErrors['amenities.0'][0] }}
                    </p>
                </div>

                <TImageUpload
                    v-model:file="coverFile"
                    v-model:removed="coverRemoved"
                    :current-url="currentCoverUrl"
                    label="Cover image"
                    :error="typeErrors.image?.[0]"
                />
                <TGalleryUpload
                    v-model:files="galleryFiles"
                    v-model:removed-ids="galleryRemovedIds"
                    :current-images="currentGallery"
                    label="Gallery"
                    :error="typeErrors['images.0']?.[0]"
                />

                <TSwitch v-if="editingType" v-model="typeForm.is_active" label="Bookable" />
                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="showTypeModal = false">Cancel</TButton>
                <TButton type="button" :loading="savingType" @click="saveType">
                    {{ editingType ? 'Save changes' : 'Create room type' }}
                </TButton>
            </template>
        </TModal>

        <TModal v-model:show="showRoomModal" @close="showRoomModal = false">
            <template #title>{{ editingRoom ? 'Edit room' : `Add room to ${roomTypeForNewRoom?.name}` }}</template>

            <form class="space-y-4" @submit.prevent="saveRoom">
                <!-- A room is now just a numbered unit: its price, capacity and
                     description all live on the type. -->
                <TInput
                    id="room_number"
                    v-model="roomForm.room_number"
                    label="Room number"
                    :error="roomErrors.room_number?.[0]"
                />
                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="showRoomModal = false">Cancel</TButton>
                <TButton type="button" :loading="savingRoom" @click="saveRoom">
                    {{ editingRoom ? 'Save changes' : 'Add room' }}
                </TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
