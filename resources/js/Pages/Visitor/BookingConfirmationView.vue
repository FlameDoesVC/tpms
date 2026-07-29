<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TInput from '@/Components/ui/TInput.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
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

const ids = computed(() => String(route.query.ids ?? '').split(',').filter(Boolean).map(Number));
const bookings = computed(() => hotelStore.activeBookings);
const isConfirmed = computed(() => bookings.value.length > 0 && bookings.value.every((b) => b.status === 'confirmed'));
const totalPrice = computed(() => bookings.value.reduce((sum, b) => sum + Number(b.total_price), 0).toFixed(2));

onMounted(() => hotelStore.fetchBookings(ids.value));

const pay = async () => {
    payError.value = '';
    if (!cardNumber.value || !expiry.value || !cvv.value) {
        payError.value = 'Fill in all payment fields.';
        return;
    }

    paying.value = true;
    try {
        // Payment gateway integration is out of scope for now — this simply
        // marks every booking in the group confirmed once the mock card form is filled in.
        await hotelStore.confirmBookings(ids.value, { silent: true });
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
            <TPageHeader title="Booking Confirmation" />
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div v-if="hotelStore.loading.activeBooking" class="text-foreground-muted">Loading booking...</div>
                <div v-else-if="hotelStore.error.activeBooking" class="text-danger">
                    {{ hotelStore.error.activeBooking }}
                </div>

                <template v-else-if="bookings.length">
                    <div class="elevated rounded-xl border bg-surface p-6">
                        <h3 class="font-semibold text-foreground">
                            Summary
                            <span v-if="bookings.length > 1" class="font-normal text-foreground-muted">({{ bookings.length }} rooms)</span>
                        </h3>

                        <div class="mt-4 divide-y divide-[rgb(var(--color-border))]">
                            <dl v-for="booking in bookings" :key="booking.id" class="space-y-2 py-3 text-sm first:pt-0">
                                <div class="flex justify-between">
                                    <dt class="text-foreground-muted">Hotel</dt>
                                    <dd class="text-foreground">{{ booking.room?.hotel?.name }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-foreground-muted">Room</dt>
                                    <dd class="capitalize text-foreground">
                                        {{ booking.room?.type }} - {{ booking.room?.room_number }} ({{ booking.guests_count }} guests)
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-foreground-muted">Dates</dt>
                                    <dd class="text-foreground">{{ booking.check_in_date?.slice(0, 10) }} to {{ booking.check_out_date?.slice(0, 10) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-foreground-muted">Price</dt>
                                    <dd class="text-foreground">${{ booking.total_price }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-foreground-muted">Confirmation number</dt>
                                    <dd class="font-mono text-foreground">{{ booking.reference_code }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="mt-2 flex justify-between border-t pt-3 text-sm font-semibold">
                            <span class="text-foreground">Total</span>
                            <span class="text-foreground">${{ totalPrice }}</span>
                        </div>
                    </div>

                    <div v-if="isConfirmed" class="mt-6 space-y-6">
                        <div class="rounded-xl bg-success-soft p-6 text-center">
                            <p class="text-lg font-semibold text-success">Booking confirmed!</p>
                            <p class="mt-1 text-sm text-success">
                                Confirmation number{{ bookings.length > 1 ? 's' : '' }}:
                                {{ bookings.map((b) => b.reference_code).join(', ') }}
                            </p>
                            <div class="mt-2 flex justify-center gap-4">
                                <router-link
                                    :to="{ name: 'bookings.my' }"
                                    class="text-sm font-medium text-success underline"
                                >
                                    View my bookings
                                </router-link>
                                <router-link
                                    :to="{ name: 'ferry.book' }"
                                    class="text-sm font-medium text-success underline"
                                >
                                    Book a ferry ticket next
                                </router-link>
                            </div>
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

                    <form v-else @submit.prevent="pay" class="mt-6 space-y-4 rounded-xl border bg-surface p-6">
                        <h3 class="font-semibold text-foreground">Payment</h3>

                        <TInput id="card_number" v-model="cardNumber" label="Card Number" placeholder="4242 4242 4242 4242" />
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <TInput id="expiry" v-model="expiry" label="Expiry" placeholder="MM/YY" />
                            </div>
                            <div class="w-24">
                                <TInput id="cvv" v-model="cvv" label="CVV" placeholder="123" />
                            </div>
                        </div>

                        <p v-if="payError" class="text-sm text-danger">{{ payError }}</p>

                        <TButton :disabled="paying" :loading="paying" type="submit">
                            {{ paying ? 'Processing...' : `Pay $${totalPrice}` }}
                        </TButton>
                    </form>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
