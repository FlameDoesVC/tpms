<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TCard from '@/Components/ui/TCard.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import PaymentForm from '@/Components/PaymentForm.vue';
import { useHotelStore } from '@/stores/hotel';
import { useAuthStore } from '@/stores/auth';
import { showToast } from '@/composables/useToast';
import { formatDateRange, formatMoney, nightsBetween } from '@/utils/format';

const route = useRoute();
const hotelStore = useHotelStore();
const auth = useAuthStore();

const paying = ref(false);
const payError = ref('');

// Filtered to real integers: a hand-edited or truncated ?ids= would otherwise
// send NaN to the API and come back as an unexplained failure.
const ids = computed(() =>
    String(route.query.ids ?? '')
        .split(',')
        .map((part) => Number(part.trim()))
        .filter((n) => Number.isInteger(n) && n > 0)
);

const bookings = computed(() => hotelStore.activeBookings);
const isConfirmed = computed(() => bookings.value.length > 0 && bookings.value.every((b) => b.status === 'confirmed'));
const totalPrice = computed(() => bookings.value.reduce((sum, b) => sum + Number(b.total_price), 0));

onMounted(() => {
    if (ids.value.length) hotelStore.fetchBookings(ids.value);
});

const pay = async () => {
    payError.value = '';
    paying.value = true;
    try {
        // Payment gateway integration is out of scope for now - this simply
        // marks every booking in the group confirmed once the mock card form
        // is filled in.
        await hotelStore.confirmBookings(ids.value, { silent: true });
        showToast('Booking confirmed.', 'success');
    } catch (e) {
        // The server's own message distinguishes an expired session from an
        // already-cancelled booking; a blanket "try again" hides both.
        payError.value = e.response?.data?.message
            ?? Object.values(e.response?.data?.errors ?? {}).flat().join(' ')
            ?? '';
        if (!payError.value) payError.value = 'Payment failed. Please try again.';
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
            <div class="mx-auto max-w-2xl space-y-6 sm:px-6 lg:px-8">
                <p v-if="hotelStore.loading.activeBooking" class="text-foreground-muted">Loading booking…</p>

                <div
                    v-else-if="hotelStore.error.activeBooking"
                    class="rounded-xl border border-danger bg-danger-soft p-4 text-sm text-danger"
                >
                    {{ hotelStore.error.activeBooking }}
                </div>

                <template v-else-if="bookings.length">
                    <TCard icon="hotel" :title="bookings.length > 1 ? `Summary · ${bookings.length} rooms` : 'Summary'">
                        <div class="divide-y divide-[rgb(var(--color-border))]">
                            <dl v-for="booking in bookings" :key="booking.id" class="space-y-2 py-3 text-sm first:pt-0">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-foreground-muted">Hotel</dt>
                                    <dd class="text-right text-foreground">{{ booking.room?.hotel?.name }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-foreground-muted">Room</dt>
                                    <dd class="text-right capitalize text-foreground">
                                        {{ booking.room?.type }} · no. {{ booking.room?.room_number }} ·
                                        {{ booking.guests_count }} guest{{ booking.guests_count === 1 ? '' : 's' }}
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-foreground-muted">Dates</dt>
                                    <dd class="text-right text-foreground">
                                        {{ formatDateRange(booking.check_in_date, booking.check_out_date) }}
                                        <span class="text-foreground-muted">
                                            ({{ nightsBetween(booking.check_in_date, booking.check_out_date) }}
                                            night{{ nightsBetween(booking.check_in_date, booking.check_out_date) === 1 ? '' : 's' }})
                                        </span>
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-foreground-muted">Price</dt>
                                    <dd class="text-right text-foreground">{{ formatMoney(booking.total_price) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-foreground-muted">Confirmation number</dt>
                                    <dd class="text-right font-mono text-foreground">{{ booking.reference_code }}</dd>
                                </div>
                            </dl>
                        </div>

                        <template #footer>
                            <div class="flex w-full justify-between text-sm font-semibold">
                                <span class="text-foreground">Total</span>
                                <span class="text-foreground">{{ formatMoney(totalPrice) }}</span>
                            </div>
                        </template>
                    </TCard>

                    <div v-if="isConfirmed" class="space-y-6" aria-live="polite">
                        <div class="rounded-xl bg-success-soft p-6 text-center">
                            <span class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-success text-white">
                                <TIcon name="check" :size="22" />
                            </span>
                            <p class="mt-3 text-lg font-semibold text-success">Booking confirmed</p>
                            <p class="mt-1 font-mono text-sm text-success">
                                {{ bookings.map((b) => b.reference_code).join(', ') }}
                            </p>
                            <div class="mt-4 flex flex-wrap justify-center gap-3">
                                <router-link :to="{ name: 'trips', query: { tab: 'hotel' } }">
                                    <TButton size="sm">View my stays</TButton>
                                </router-link>
                                <router-link :to="{ name: 'ferry.book' }">
                                    <TButton variant="secondary" size="sm">Book a ferry</TButton>
                                </router-link>
                                <router-link :to="{ name: 'themepark.home' }">
                                    <TButton variant="secondary" size="sm">Browse events</TButton>
                                </router-link>
                            </div>
                        </div>

                        <div v-if="auth.isGuest" class="rounded-xl border bg-primary-soft p-4 text-sm text-primary">
                            <p>You checked out as a guest. Log in or create an account to keep this booking.</p>
                            <div class="mt-3 flex gap-3">
                                <router-link :to="{ name: 'login', query: { redirect: route.fullPath } }">
                                    <TButton size="sm">Log in</TButton>
                                </router-link>
                                <router-link :to="{ name: 'register', query: { redirect: route.fullPath } }">
                                    <TButton variant="secondary" size="sm">Create account</TButton>
                                </router-link>
                            </div>
                        </div>
                    </div>

                    <TCard v-else icon="card" title="Payment">
                        <PaymentForm
                            :total="totalPrice"
                            :processing="paying"
                            :error="payError"
                            @submit="pay"
                        />
                    </TCard>
                </template>

                <!-- Reached with a stale, truncated or someone else's ?ids= -
                     previously this rendered a completely blank page. -->
                <TEmptyState
                    v-else
                    title="Booking not found"
                    description="This link may have expired, or the booking may belong to another account."
                    icon="search"
                >
                    <template #action>
                        <router-link :to="{ name: 'trips', query: { tab: 'hotel' } }">
                            <TButton size="sm">View my stays</TButton>
                        </router-link>
                    </template>
                </TEmptyState>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
