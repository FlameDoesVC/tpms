<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
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
            <TPageHeader title="Checkout" />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="confirmation" class="space-y-6">
                    <div class="rounded-xl bg-success-soft p-6 text-center">
                        <p class="text-lg font-semibold text-success">All set!</p>
                        <p class="mt-1 text-sm text-success">
                            {{ confirmation.hotel.length + confirmation.ferry.length + confirmation.themepark.length }} item(s) confirmed.
                        </p>
                    </div>

                    <div v-if="confirmation.hotel.length" class="elevated rounded-xl border bg-surface p-4">
                        <p class="font-semibold text-foreground">Hotel</p>
                        <p v-for="b in confirmation.hotel" :key="b.id" class="mt-1 text-sm text-foreground-secondary">
                            {{ b.reference_code }} - ${{ b.total_price }}
                        </p>
                    </div>
                    <div v-if="confirmation.ferry.length" class="elevated rounded-xl border bg-surface p-4">
                        <p class="font-semibold text-foreground">Ferry</p>
                        <p v-for="t in confirmation.ferry" :key="t.id" class="mt-1 text-sm text-foreground-secondary">
                            {{ t.reference_code }} - seat {{ t.seat_number }}
                        </p>
                    </div>
                    <div v-if="confirmation.themepark.length" class="elevated rounded-xl border bg-surface p-4">
                        <p class="font-semibold text-foreground">Theme Park</p>
                        <p v-for="b in confirmation.themepark" :key="b.id" class="mt-1 text-sm text-foreground-secondary">
                            Booking #{{ b.id }} - {{ b.ticket_count }} ticket(s)
                        </p>
                    </div>

                    <div class="flex justify-center gap-4 text-sm">
                        <router-link :to="{ name: 'bookings.my' }" class="font-medium text-primary underline">My Bookings</router-link>
                        <router-link :to="{ name: 'ferry.my-tickets' }" class="font-medium text-primary underline">My Tickets</router-link>
                        <router-link :to="{ name: 'themepark.my-bookings' }" class="font-medium text-primary underline">My Theme Park Bookings</router-link>
                    </div>

                    <div v-if="auth.isGuest" class="rounded-xl border bg-primary-soft p-4 text-sm text-primary">
                        <p>You checked out as a guest. Log in or create an account to save this booking.</p>
                        <div class="mt-3 flex gap-3">
                            <router-link
                                :to="{ name: 'login', query: { redirect: route.fullPath } }"
                                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-hover"
                            >
                                Log In
                            </router-link>
                            <router-link
                                :to="{ name: 'register', query: { redirect: route.fullPath } }"
                                class="rounded-lg border border-strong bg-surface px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-surface-hover"
                            >
                                Create Account
                            </router-link>
                        </div>
                    </div>
                </div>

                <template v-else>
                    <TEmptyState
                        v-if="cart.items.length === 0"
                        title="Your cart is empty"
                        description="Add a hotel room, ferry ticket or theme park slot to get started."
                        icon="inbox"
                    />

                    <template v-else>
                        <div class="elevated rounded-xl border bg-surface p-6">
                            <h3 class="font-semibold text-foreground">Order Summary</h3>
                            <div class="mt-4 divide-y divide-[rgb(var(--color-border))]">
                                <div v-for="item in cart.items" :key="item.id" class="flex items-start justify-between gap-2 py-3 text-sm">
                                    <div>
                                        <TBadge variant="primary">{{ typeLabel(item.type) }}</TBadge>
                                        <template v-if="item.type === 'hotel'">
                                            <p class="mt-1 font-medium text-foreground">{{ item.hotelName }} - {{ item.roomType }}</p>
                                            <p class="text-xs text-foreground-muted">{{ item.quantity }} room(s), {{ item.checkIn }} to {{ item.checkOut }}</p>
                                        </template>
                                        <template v-else-if="item.type === 'ferry'">
                                            <p class="mt-1 font-medium text-foreground">{{ item.ferryName }}</p>
                                            <p class="text-xs text-foreground-muted">
                                                {{ item.departureDate }} at {{ item.departureTime }} - {{ item.seatNumbers.length }} seat(s)
                                                ({{ item.paymentMethod === 'cash' ? 'cash on board' : 'online' }})
                                            </p>
                                        </template>
                                        <template v-else>
                                            <p class="mt-1 font-medium text-foreground">{{ item.eventName }}</p>
                                            <p class="text-xs text-foreground-muted">{{ item.slotDate }} at {{ item.slotTime }} - {{ item.ticketCount }} ticket(s)</p>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-medium text-foreground">${{ Number(item.subtotal).toFixed(2) }}</span>
                                        <button type="button" @click="cart.removeItem(item.id)" class="text-xs text-danger hover:underline">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 flex justify-between border-t pt-3 text-sm font-semibold">
                                <span class="text-foreground">Total due now</span>
                                <span class="text-foreground">${{ cart.onlineTotal.toFixed(2) }}</span>
                            </div>
                            <p v-if="cart.cashDueTotal > 0" class="mt-1 text-xs text-foreground-muted">
                                Plus ${{ cart.cashDueTotal.toFixed(2) }} cash due on board for ferry tickets.
                            </p>
                        </div>

                        <form @submit.prevent="checkout" class="space-y-4 rounded-xl border bg-surface p-6">
                            <h3 class="font-semibold text-foreground">Payment</h3>

                            <template v-if="paymentRequired">
                                <TInput v-model="cardNumber" label="Card Number" placeholder="4242 4242 4242 4242" />
                                <div class="flex gap-4">
                                    <div class="flex-1">
                                        <TInput v-model="expiry" label="Expiry" placeholder="MM/YY" />
                                    </div>
                                    <div class="w-24">
                                        <TInput v-model="cvv" label="CVV" placeholder="123" />
                                    </div>
                                </div>
                            </template>
                            <p v-else class="text-sm text-foreground-muted">No online payment needed - everything in your cart is cash on board.</p>

                            <p v-if="payError" class="text-sm text-danger">{{ payError }}</p>

                            <TButton :disabled="paying" :loading="paying" type="submit">
                                {{ paying ? 'Processing...' : `Pay $${cart.onlineTotal.toFixed(2)} & Confirm` }}
                            </TButton>
                        </form>
                    </template>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
