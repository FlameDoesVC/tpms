<script setup>
import { computed, onMounted, ref } from 'vue';
import StaffLayout from '@/Layouts/StaffLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TCard from '@/Components/ui/TCard.vue';
import TModal from '@/Components/ui/TModal.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TImageUpload from '@/Components/ui/TImageUpload.vue';
import TCheckbox from '@/Components/ui/TCheckbox.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import StaffToolbar from '@/Components/StaffToolbar.vue';
import { useHotelStore } from '@/stores/hotel';
import { useConfirm } from '@/composables/useConfirm';
import { showToast } from '@/composables/useToast';

const hotelStore = useHotelStore();
const confirm = useConfirm();
const showModal = ref(false);
const editingHotel = ref(null);
const errors = ref({});
const saving = ref(false);

const search = ref('');

const visibleHotels = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (!query) return hotelStore.hotels;
    return hotelStore.hotels.filter((h) => `${h.name} ${h.address}`.toLowerCase().includes(query));
});

const summary = computed(() => {
    const total = hotelStore.hotels.length;
    if (!total) return null;
    const shown = visibleHotels.value.length;
    return shown === total ? `${total} hotel${total === 1 ? '' : 's'}` : `${shown} of ${total} hotels`;
});

const emptyForm = () => ({
    name: '',
    description: '',
    address: '',
    total_rooms: 0,
    is_active: true,
});
const form = ref(emptyForm());
const imageFile = ref(null);
const imageRemoved = ref(false);
const currentImageUrl = ref(null);

onMounted(() => hotelStore.fetchHotels({ all: true }));

const openAddModal = () => {
    editingHotel.value = null;
    form.value = emptyForm();
    imageFile.value = null;
    imageRemoved.value = false;
    currentImageUrl.value = null;
    errors.value = {};
    showModal.value = true;
};

const openEditModal = (hotel) => {
    editingHotel.value = hotel;
    form.value = {
        name: hotel.name,
        description: hotel.description ?? '',
        address: hotel.address,
        total_rooms: hotel.total_rooms,
        is_active: hotel.is_active,
    };
    imageFile.value = null;
    imageRemoved.value = false;
    currentImageUrl.value = hotel.image_url ?? null;
    errors.value = {};
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
};

// Only reaches for FormData when there's actually a file to send or an
// existing image to clear - plain JSON keeps working for every other edit.
const buildPayload = () => {
    const fields = { ...form.value, total_rooms: Number(form.value.total_rooms) || 0 };
    if (!imageFile.value && !imageRemoved.value) {
        return fields;
    }
    const payload = new FormData();
    Object.entries(fields).forEach(([key, value]) => {
        if (value === null || value === undefined) return;
        // Laravel's `boolean` rule doesn't accept the strings "true"/"false"
        // that FormData.append would otherwise coerce a JS boolean into.
        payload.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : value);
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
        if (editingHotel.value) {
            await hotelStore.updateHotel(editingHotel.value.id, payload);
        } else {
            await hotelStore.createHotel(payload);
        }
        showToast(editingHotel.value ? 'Hotel updated.' : 'Hotel created.', 'success');
        closeModal();
    } catch (e) {
        errors.value = e.response?.data?.errors ?? {};
        if (Object.keys(errors.value).length === 0) {
            showToast(e.response?.data?.message ?? 'Could not save the hotel.');
        }
    } finally {
        saving.value = false;
    }
};

const remove = async (hotel) => {
    const ok = await confirm({
        title: `Delete ${hotel.name}?`,
        message: 'The hotel and its rooms are removed for good. Bookings already made against it are kept.',
        confirmLabel: 'Delete hotel',
        danger: true,
    });
    if (!ok) return;

    try {
        await hotelStore.deleteHotel(hotel.id);
        showToast(`${hotel.name} deleted.`, 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not delete this hotel.');
    }
};
</script>

<template>
    <StaffLayout>
        <template #header>
            <TPageHeader title="Hotel Management" icon="hotel" compact />
        </template>

        <div class="space-y-5">
            <StaffToolbar title="Properties" :summary="summary">
                <template #actions>
                    <TButton @click="openAddModal">
                        <TIcon name="plus" :size="16" />
                        Add hotel
                    </TButton>
                </template>

                <TInput
                    v-model="search"
                    type="search"
                    label="Search"
                    placeholder="Name or address"
                    class="min-w-[16rem] flex-1"
                >
                    <template #prefix><TIcon name="search" :size="16" /></template>
                </TInput>
            </StaffToolbar>

            <TCard :padding="false">
                <div v-if="hotelStore.loading.hotels" class="p-8 text-center text-sm text-foreground-muted">
                    Loading hotels…
                </div>
                <div v-else-if="hotelStore.error.hotels" class="p-8 text-center text-sm text-danger">
                    {{ hotelStore.error.hotels }}
                </div>
                <div v-else-if="hotelStore.hotels.length === 0" class="p-4">
                    <TEmptyState title="No hotels yet" description="Add the first property." icon="hotel">
                        <template #action>
                            <TButton @click="openAddModal">Add hotel</TButton>
                        </template>
                    </TEmptyState>
                </div>
                <div v-else-if="visibleHotels.length === 0" class="p-4">
                    <TEmptyState
                        title="No hotels match"
                        description="Try a different name or address."
                        icon="search"
                    />
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Rooms</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="hotel in visibleHotels" :key="hotel.id">
                                <td class="font-medium text-foreground">{{ hotel.name }}</td>
                                <td>{{ hotel.address }}</td>
                                <td>{{ hotel.total_rooms }}</td>
                                <td>
                                    <TBadge :variant="hotel.is_active ? 'success' : 'neutral'">
                                        {{ hotel.is_active ? 'Active' : 'Inactive' }}
                                    </TBadge>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-primary"
                                            :aria-label="`Edit ${hotel.name}`"
                                            title="Edit"
                                            @click="openEditModal(hotel)"
                                        >
                                            <TIcon name="edit" :size="16" />
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg p-1.5 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                                            :aria-label="`Delete ${hotel.name}`"
                                            title="Delete"
                                            @click="remove(hotel)"
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
            <template #title>{{ editingHotel ? 'Edit hotel' : 'Add hotel' }}</template>

            <form @submit.prevent="save" class="space-y-4">
                <TInput id="name" v-model="form.name" label="Name" :error="errors.name?.[0]" />
                <TInput id="address" v-model="form.address" label="Address" :error="errors.address?.[0]" />
                <TInput
                    id="description"
                    v-model="form.description"
                    label="Description"
                    :error="errors.description?.[0]"
                />
                <TInput
                    id="total_rooms"
                    v-model="form.total_rooms"
                    type="number"
                    min="0"
                    label="Total rooms"
                    :error="errors.total_rooms?.[0]"
                />
                <TImageUpload
                    v-model:file="imageFile"
                    v-model:removed="imageRemoved"
                    :current-url="currentImageUrl"
                    label="Image"
                    :error="errors.image?.[0]"
                />
                <TCheckbox v-if="editingHotel" v-model="form.is_active" label="Active" />
                <button type="submit" class="hidden" />
            </form>

            <template #footer>
                <TButton variant="secondary" type="button" @click="closeModal">Cancel</TButton>
                <TButton type="button" :loading="saving" @click="save">
                    {{ editingHotel ? 'Save changes' : 'Create hotel' }}
                </TButton>
            </template>
        </TModal>
    </StaffLayout>
</template>
