<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const cart = useCartStore();
const auth = useAuthStore();

const cardNumber = ref('');
const expiry = ref('');
const cvv = ref('');
const paying = ref(false);
const payError = ref('');
const confirmation = ref(null);

const paymentRequired = computed(() => cart.onlineTotal > 0);

const typeLabel = (type) => ({ hotel: 'Hotel', ferry: 'Ferry', themepark: 'Theme Park' }[type]);

// Everything is created server-side in one DB transaction - either all of it
// goes through, or none of it does. On failure the cart is completely
// untouched (nothing was ever removed from it to begin with).
const checkout = async () => {
    payError.value = '';
    if (paymentRequired.value && (!cardNumber.value || !expiry.value || !cvv.value)) {
        payError.value = 'Fill in all payment fields.';
        return;
    }

    paying.value = true;
    try {
        const result = await cart.checkout();
        cart.clear();
        if (!auth.isAuthenticated) await auth.fetchUser();
        confirmation.value = result;
    } catch (e) {
        payError.value = Object.values(e.response?.data?.errors ?? {}).flat().join(' ')
            || 'Could not complete checkout. Nothing was charged - your cart is unchanged.';
    } finally {
        paying.value = false;
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Checkout
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="confirmation" class="space-y-6">
                    <div class="rounded-lg bg-green-50 p-6 text-center">
                        <p class="text-lg font-semibold text-green-800">All set!</p>
                        <p class="mt-1 text-sm text-green-700">
                            {{ confirmation.hotel.length + confirmation.ferry.length + confirmation.themepark.length }} item(s) confirmed.
                        </p>
                    </div>

                    <div v-if="confirmation.hotel.length" class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="font-semibold text-gray-900">Hotel</p>
                        <p v-for="b in confirmation.hotel" :key="b.id" class="mt-1 text-sm text-gray-600">
                            {{ b.reference_code }} - ${{ b.total_price }}
                        </p>
                    </div>
                    <div v-if="confirmation.ferry.length" class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="font-semibold text-gray-900">Ferry</p>
                        <p v-for="t in confirmation.ferry" :key="t.id" class="mt-1 text-sm text-gray-600">
                            {{ t.reference_code }} - seat {{ t.seat_number }}
                        </p>
                    </div>
                    <div v-if="confirmation.themepark.length" class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="font-semibold text-gray-900">Theme Park</p>
                        <p v-for="b in confirmation.themepark" :key="b.id" class="mt-1 text-sm text-gray-600">
                            Booking #{{ b.id }} - {{ b.ticket_count }} ticket(s)
                        </p>
                    </div>

                    <div class="flex justify-center gap-4 text-sm">
                        <router-link :to="{ name: 'bookings.my' }" class="font-medium text-indigo-600 underline">My Bookings</router-link>
                        <router-link :to="{ name: 'ferry.my-tickets' }" class="font-medium text-indigo-600 underline">My Tickets</router-link>
                        <router-link :to="{ name: 'themepark.my-bookings' }" class="font-medium text-indigo-600 underline">My Theme Park Bookings</router-link>
                    </div>

                    <div v-if="auth.isGuest" class="rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-800">
                        <p>You checked out as a guest. Log in or create an account to save this booking.</p>
                        <div class="mt-3 flex gap-3">
                            <router-link
                                :to="{ name: 'login', query: { redirect: route.fullPath } }"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                Log In
                            </router-link>
                            <router-link
                                :to="{ name: 'register', query: { redirect: route.fullPath } }"
                                class="rounded-md border border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-600 hover:bg-indigo-100"
                            >
                                Create Account
                            </router-link>
                        </div>
                    </div>
                </div>

                <template v-else>
                    <div v-if="cart.items.length === 0" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">
                        Your cart is empty.
                    </div>

                    <template v-else>
                        <div class="rounded-lg bg-white p-6 shadow-sm">
                            <h3 class="font-semibold text-gray-900">Order Summary</h3>
                            <div class="mt-4 divide-y divide-gray-100">
                                <div v-for="item in cart.items" :key="item.id" class="flex items-start justify-between gap-2 py-3 text-sm">
                                    <div>
                                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">
                                            {{ typeLabel(item.type) }}
                                        </span>
                                        <template v-if="item.type === 'hotel'">
                                            <p class="mt-1 font-medium text-gray-900">{{ item.hotelName }} - {{ item.roomType }}</p>
                                            <p class="text-xs text-gray-500">{{ item.quantity }} room(s), {{ item.checkIn }} to {{ item.checkOut }}</p>
                                        </template>
                                        <template v-else-if="item.type === 'ferry'">
                                            <p class="mt-1 font-medium text-gray-900">{{ item.ferryName }}</p>
                                            <p class="text-xs text-gray-500">
                                                {{ item.departureDate }} at {{ item.departureTime }} - {{ item.seatNumbers.length }} seat(s)
                                                ({{ item.paymentMethod === 'cash' ? 'cash on board' : 'online' }})
                                            </p>
                                        </template>
                                        <template v-else>
                                            <p class="mt-1 font-medium text-gray-900">{{ item.eventName }}</p>
                                            <p class="text-xs text-gray-500">{{ item.slotDate }} at {{ item.slotTime }} - {{ item.ticketCount }} ticket(s)</p>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-medium text-gray-900">${{ Number(item.subtotal).toFixed(2) }}</span>
                                        <button type="button" @click="cart.removeItem(item.id)" class="text-xs text-red-600 hover:underline">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 flex justify-between border-t border-gray-200 pt-3 text-sm font-semibold">
                                <span class="text-gray-900">Total due now</span>
                                <span class="text-gray-900">${{ cart.onlineTotal.toFixed(2) }}</span>
                            </div>
                            <p v-if="cart.cashDueTotal > 0" class="mt-1 text-xs text-gray-500">
                                Plus ${{ cart.cashDueTotal.toFixed(2) }} cash due on board for ferry tickets.
                            </p>
                        </div>

                        <form @submit.prevent="checkout" class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                            <h3 class="font-semibold text-gray-900">Payment</h3>

                            <template v-if="paymentRequired">
                                <div>
                                    <InputLabel value="Card Number" />
                                    <TextInput v-model="cardNumber" class="mt-1 block w-full" placeholder="4242 4242 4242 4242" />
                                </div>
                                <div class="flex gap-4">
                                    <div class="flex-1">
                                        <InputLabel value="Expiry" />
                                        <TextInput v-model="expiry" class="mt-1 block w-full" placeholder="MM/YY" />
                                    </div>
                                    <div class="w-24">
                                        <InputLabel value="CVV" />
                                        <TextInput v-model="cvv" class="mt-1 block w-full" placeholder="123" />
                                    </div>
                                </div>
                            </template>
                            <p v-else class="text-sm text-gray-500">No online payment needed - everything in your cart is cash on board.</p>

                            <p v-if="payError" class="text-sm text-red-600">{{ payError }}</p>

                            <PrimaryButton :disabled="paying" type="submit">
                                {{ paying ? 'Processing...' : `Pay $${cart.onlineTotal.toFixed(2)} & Confirm` }}
                            </PrimaryButton>
                        </form>
                    </template>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
