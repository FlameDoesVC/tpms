<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useThemeParkStore } from '@/stores/themepark';

const themeParkStore = useThemeParkStore();
const bookingId = ref('');
const booking = ref(null);
const lookupError = ref('');

const lookup = async () => {
    lookupError.value = '';
    booking.value = null;
    try {
        booking.value = await themeParkStore.lookupTicket(bookingId.value);
    } catch {
        lookupError.value = 'Booking not found.';
    }
};

const confirmUsed = async () => {
    try {
        booking.value = await themeParkStore.validateTicket(booking.value.id);
    } catch {
        lookupError.value = 'This ticket has already been used.';
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Ticket Validation
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-lg space-y-6 sm:px-6 lg:px-8">
                <form @submit.prevent="lookup" class="flex gap-2 rounded-lg bg-white p-4 shadow-sm">
                    <input
                        v-model="bookingId"
                        placeholder="Booking ID or scan QR"
                        class="flex-1 rounded-md border-gray-300 shadow-sm"
                    />
                    <PrimaryButton type="submit">Look Up</PrimaryButton>
                </form>

                <div v-if="lookupError" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
                    {{ lookupError }}
                </div>

                <div v-if="booking" class="rounded-lg p-6" :class="booking.status === 'used' ? 'bg-red-50' : 'bg-green-50'">
                    <p class="font-semibold" :class="booking.status === 'used' ? 'text-red-800' : 'text-green-800'">
                        {{ booking.status === 'used' ? 'Already Used' : booking.status === 'cancelled' ? 'Cancelled' : 'Valid Ticket' }}
                    </p>
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Visitor</dt>
                            <dd>{{ booking.user?.name ?? booking.visitor_name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Event</dt>
                            <dd>{{ booking.slot?.event?.name }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Slot Time</dt>
                            <dd>{{ booking.slot?.slot_date?.slice(0, 10) }} {{ booking.slot?.slot_time }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Tickets</dt>
                            <dd>{{ booking.ticket_count }}</dd>
                        </div>
                    </dl>

                    <PrimaryButton
                        v-if="booking.status === 'confirmed'"
                        class="mt-4"
                        @click="confirmUsed"
                    >
                        Confirm Used
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
