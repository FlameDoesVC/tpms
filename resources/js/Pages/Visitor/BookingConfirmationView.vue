<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import ClaimGuestAccountForm from '@/Components/ClaimGuestAccountForm.vue';
import { useHotelStore } from '@/stores/hotel';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const hotelStore = useHotelStore();
const auth = useAuthStore();

const cardNumber = ref('');
const expiry = ref('');
const cvv = ref('');
const paying = ref(false);
const payError = ref('');

const booking = computed(() => hotelStore.activeBooking);
const isConfirmed = computed(() => booking.value?.status === 'confirmed');

onMounted(() => hotelStore.fetchBooking(route.params.id));

const pay = async () => {
    payError.value = '';
    if (!cardNumber.value || !expiry.value || !cvv.value) {
        payError.value = 'Fill in all payment fields.';
        return;
    }

    paying.value = true;
    try {
        // Payment gateway integration is out of scope for now — this simply
        // marks the booking confirmed once the mock card form is filled in.
        await hotelStore.confirmBooking(route.params.id);
    } catch {
        payError.value = 'Payment failed. Please try again.';
    } finally {
        paying.value = false;
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Booking Confirmation
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div v-if="hotelStore.loading.activeBooking" class="text-gray-500">Loading booking...</div>
                <div v-else-if="hotelStore.error.activeBooking" class="text-red-600">
                    {{ hotelStore.error.activeBooking }}
                </div>

                <template v-else-if="booking">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Summary</h3>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Hotel</dt>
                                <dd class="text-gray-900">{{ booking.room?.hotel?.name }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Room</dt>
                                <dd class="text-gray-900 capitalize">
                                    {{ booking.room?.type }} - {{ booking.room?.room_number }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Dates</dt>
                                <dd class="text-gray-900">{{ booking.check_in_date }} to {{ booking.check_out_date }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Total price</dt>
                                <dd class="font-medium text-gray-900">${{ booking.total_price }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Confirmation number</dt>
                                <dd class="font-mono text-gray-900">#{{ booking.id }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div v-if="isConfirmed" class="mt-6 space-y-6">
                        <div class="rounded-lg bg-green-50 p-6 text-center">
                            <p class="text-lg font-semibold text-green-800">Booking confirmed!</p>
                            <p class="mt-1 text-sm text-green-700">
                                Your confirmation number is #{{ booking.id }}.
                            </p>
                            <div class="mt-2 flex justify-center gap-4">
                                <router-link
                                    :to="{ name: 'bookings.my' }"
                                    class="text-sm font-medium text-green-800 underline"
                                >
                                    View my bookings
                                </router-link>
                                <router-link
                                    :to="{ name: 'ferry.book' }"
                                    class="text-sm font-medium text-green-800 underline"
                                >
                                    Book a ferry ticket next
                                </router-link>
                            </div>
                        </div>

                        <ClaimGuestAccountForm v-if="auth.isGuest" />
                    </div>

                    <form v-else @submit.prevent="pay" class="mt-6 space-y-4 rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-gray-900">Payment</h3>

                        <div>
                            <InputLabel for="card_number" value="Card Number" />
                            <TextInput id="card_number" v-model="cardNumber" class="mt-1 block w-full" placeholder="4242 4242 4242 4242" />
                        </div>
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <InputLabel for="expiry" value="Expiry" />
                                <TextInput id="expiry" v-model="expiry" class="mt-1 block w-full" placeholder="MM/YY" />
                            </div>
                            <div class="w-24">
                                <InputLabel for="cvv" value="CVV" />
                                <TextInput id="cvv" v-model="cvv" class="mt-1 block w-full" placeholder="123" />
                            </div>
                        </div>

                        <p v-if="payError" class="text-sm text-red-600">{{ payError }}</p>

                        <PrimaryButton :disabled="paying" type="submit">
                            {{ paying ? 'Processing...' : 'Pay' }}
                        </PrimaryButton>
                    </form>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
