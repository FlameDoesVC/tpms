<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ClaimGuestAccountForm from '@/Components/ClaimGuestAccountForm.vue';
import { useThemeParkStore } from '@/stores/themepark';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const themeParkStore = useThemeParkStore();
const auth = useAuthStore();

const eventId = route.params.id;
const date = ref(new Date().toISOString().slice(0, 10));
const selectedSlotId = ref(null);
const ticketCount = ref(1);
const bookingError = ref('');
const confirmedBooking = ref(null);

const load = () => themeParkStore.fetchEventSlots(eventId, date.value);
onMounted(load);

const selectedSlot = computed(() =>
    themeParkStore.slots.find((s) => s.id === selectedSlotId.value)
);

const book = async () => {
    bookingError.value = '';
    if (!selectedSlotId.value) {
        bookingError.value = 'Pick a time slot first.';
        return;
    }

    try {
        confirmedBooking.value = await themeParkStore.bookSlot(selectedSlotId.value, ticketCount.value);
        if (!auth.isAuthenticated) await auth.fetchUser();
        load();
    } catch (e) {
        if (e.response?.status === 422) {
            bookingError.value = 'Capacity exceeded for this slot. Please choose another time.';
        } else {
            bookingError.value = 'Could not complete booking.';
        }
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ themeParkStore.event?.name ?? 'Event' }}
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="themeParkStore.event" class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-gray-700">{{ themeParkStore.event.description }}</p>
                    <p class="mt-2 text-sm text-gray-500">
                        {{ themeParkStore.event.location }} - {{ themeParkStore.event.duration_minutes }} min
                    </p>
                </div>

                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <label class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" v-model="date" @change="load" class="mt-1 rounded-md border-gray-300 shadow-sm" />
                </div>

                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <p class="mb-2 text-sm font-medium text-gray-700">Time Slots</p>
                    <div v-if="themeParkStore.slots.length === 0" class="text-sm text-gray-500">
                        No slots available for this date.
                    </div>
                    <div v-else class="flex flex-wrap gap-2">
                        <button
                            v-for="slot in themeParkStore.slots"
                            :key="slot.id"
                            :disabled="slot.available_capacity < 1"
                            @click="selectedSlotId = slot.id"
                            class="rounded-md border px-3 py-2 text-sm"
                            :class="[
                                selectedSlotId === slot.id ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-300',
                                slot.available_capacity < 1 ? 'cursor-not-allowed opacity-50' : 'hover:bg-gray-50',
                            ]"
                        >
                            {{ slot.slot_time }} ({{ slot.available_capacity }} left)
                        </button>
                    </div>
                </div>

                <div v-if="selectedSlot" class="rounded-lg bg-white p-4 shadow-sm">
                    <label class="block text-sm font-medium text-gray-700">Ticket Count</label>
                    <input
                        type="number"
                        min="1"
                        :max="selectedSlot.available_capacity"
                        v-model.number="ticketCount"
                        class="mt-1 w-24 rounded-md border-gray-300 shadow-sm"
                    />
                </div>

                <p v-if="bookingError" class="text-sm text-red-600">{{ bookingError }}</p>

                <PrimaryButton :disabled="!selectedSlotId || themeParkStore.loading.booking" @click="book">
                    Book
                </PrimaryButton>

                <div v-if="confirmedBooking" class="space-y-6">
                    <div class="rounded-lg bg-green-50 p-6 text-center">
                        <p class="font-semibold text-green-800">Booking confirmed!</p>
                        <p class="mt-1 text-sm text-green-700">Booking reference #{{ confirmedBooking.id }}</p>
                    </div>

                    <ClaimGuestAccountForm v-if="auth.isGuest" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
