<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TButton from '@/Components/ui/TButton.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TCard from '@/Components/ui/TCard.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TEmptyState from '@/Components/ui/TEmptyState.vue';
import PaymentForm from '@/Components/PaymentForm.vue';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';
import { useConfirm } from '@/composables/useConfirm';
import { formatDateRange, formatDateTime, formatMoney } from '@/utils/format';
import { saveLastCheckout, loadLastCheckout, clearLastCheckout } from '@/utils/lastCheckout';

const route = useRoute();
const cart = useCartStore();
const auth = useAuthStore();
const confirm = useConfirm();

const paying = ref(false);
const payError = ref('');
const confirmation = ref(null);

const paymentRequired = computed(() => cart.onlineTotal > 0);

const typeLabel = (type) => ({ hotel: 'Hotel', ferry: 'Ferry', themepark: 'Theme Park' }[type]);

// Survives a refresh on the success screen, where the cart has already been
// cleared and there is nothing left to rebuild the receipt from. Only restored
// onto an empty cart so a new shopping session is never shadowed by an old one.
onMounted(() => {
    if (cart.items.length === 0) confirmation.value = loadLastCheckout();
});

const purchased = computed(() => ({
    hotel: confirmation.value?.hotel?.length ?? 0,
    ferry: confirmation.value?.ferry?.length ?? 0,
    themepark: confirmation.value?.themepark?.length ?? 0,
}));
const purchasedCount = computed(() =>
    purchased.value.hotel + purchased.value.ferry + purchased.value.themepark
);

const removeItem = async (item) => {
    const dependents = cart.dependentsOf(item.id);

    if (dependents.length) {
        const names = dependents.map((d) => d.ferryName).filter(Boolean).join(', ');
        const ok = await confirm({
            title: 'Remove this room?',
            message: `The ferry ${dependents.length === 1 ? 'ticket' : 'tickets'} for ${names || 'this trip'} can only be booked against a room, so ${dependents.length === 1 ? 'it' : 'they'} will be removed too.`,
            confirmLabel: 'Remove both',
            danger: true,
        });
        if (!ok) return;
    }

    cart.removeItem(item.id);
};

// Everything is created server-side in one DB transaction - either all of it
// goes through, or none of it does. On failure the cart is completely
// untouched (nothing was ever removed from it to begin with).
const checkout = async () => {
    payError.value = '';
    paying.value = true;
    try {
        const result = await cart.checkout();
        saveLastCheckout(result);
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

const dismissReceipt = () => {
    clearLastCheckout();
    confirmation.value = null;
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader title="Checkout" />
        </template>

        <div class="shell pb-8 pt-5">
            <div class="mx-auto max-w-2xl space-y-6">
                <div v-if="confirmation" class="space-y-6" aria-live="polite">
                    <div class="rounded-xl bg-success-soft p-6 text-center">
                        <span class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-success text-white">
                            <TIcon name="check" :size="22" />
                        </span>
                        <p class="mt-3 text-lg font-semibold text-success">All set!</p>
                        <p class="mt-1 text-sm text-success">
                            {{ purchasedCount }} item{{ purchasedCount === 1 ? '' : 's' }} confirmed. Keep your reference codes handy.
                        </p>
                    </div>

                    <TCard v-if="purchased.hotel" icon="hotel" title="Hotel">
                        <ul class="space-y-1 text-sm text-foreground-secondary">
                            <li v-for="b in confirmation.hotel" :key="b.id" class="flex justify-between gap-3">
                                <span class="font-mono text-foreground">{{ b.reference_code }}</span>
                                <span>{{ formatMoney(b.total_price) }}</span>
                            </li>
                        </ul>
                    </TCard>

                    <TCard v-if="purchased.ferry" icon="ferry" title="Ferry">
                        <ul class="space-y-1 text-sm text-foreground-secondary">
                            <li v-for="t in confirmation.ferry" :key="t.id" class="flex justify-between gap-3">
                                <span class="font-mono text-foreground">{{ t.reference_code }}</span>
                                <span>Seat {{ t.seat_number }}</span>
                            </li>
                        </ul>
                    </TCard>

                    <TCard v-if="purchased.themepark" icon="sparkle" title="Theme Park">
                        <ul class="space-y-1 text-sm text-foreground-secondary">
                            <li v-for="b in confirmation.themepark" :key="b.id" class="flex justify-between gap-3">
                                <span class="font-mono text-foreground">{{ b.reference_code }}</span>
                                <span>{{ b.ticket_count }} ticket{{ b.ticket_count === 1 ? '' : 's' }}</span>
                            </li>
                        </ul>
                    </TCard>

                    <!-- Only the types actually purchased get a link, so a
                         hotel-only order doesn't advertise two empty pages. -->
                    <div class="flex flex-wrap justify-center gap-3">
                        <router-link v-if="purchased.hotel" :to="{ name: 'trips', query: { tab: 'hotel' } }">
                            <TButton variant="secondary" size="sm">View stays</TButton>
                        </router-link>
                        <router-link v-if="purchased.ferry" :to="{ name: 'trips', query: { tab: 'ferry' } }">
                            <TButton variant="secondary" size="sm">View ferry tickets</TButton>
                        </router-link>
                        <router-link v-if="purchased.themepark" :to="{ name: 'trips', query: { tab: 'park' } }">
                            <TButton variant="secondary" size="sm">View park tickets</TButton>
                        </router-link>
                        <TButton size="sm" @click="dismissReceipt">Done</TButton>
                    </div>

                    <div v-if="auth.isGuest" class="rounded-xl border bg-primary-soft p-4 text-sm text-primary">
                        <p>You checked out as a guest. Log in or create an account to keep these bookings.</p>
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

                <template v-else>
                    <TEmptyState
                        v-if="cart.items.length === 0"
                        title="Your itinerary is empty"
                        description="Add a hotel room, ferry ticket or theme park slot to get started."
                        icon="inbox"
                    >
                        <template #action>
                            <div class="flex flex-wrap justify-center gap-2">
                                <router-link :to="{ name: 'hotels.index' }">
                                    <TButton size="sm">Browse hotels</TButton>
                                </router-link>
                                <router-link :to="{ name: 'themepark.home' }">
                                    <TButton variant="secondary" size="sm">Browse events</TButton>
                                </router-link>
                            </div>
                        </template>
                    </TEmptyState>

                    <template v-else>
                        <TCard icon="cart" title="Order summary">
                            <div class="divide-y divide-[rgb(var(--color-border))]">
                                <div
                                    v-for="item in cart.items"
                                    :key="item.id"
                                    class="flex items-start justify-between gap-3 py-3 text-sm first:pt-0"
                                >
                                    <div class="min-w-0">
                                        <TBadge variant="primary" size="sm">{{ typeLabel(item.type) }}</TBadge>
                                        <template v-if="item.type === 'hotel'">
                                            <p class="mt-1 font-medium text-foreground">{{ item.hotelName }} · {{ item.roomType }}</p>
                                            <p class="text-xs text-foreground-muted">
                                                {{ item.quantity }} room{{ item.quantity === 1 ? '' : 's' }} ·
                                                {{ formatDateRange(item.checkIn, item.checkOut) }}
                                            </p>
                                        </template>
                                        <template v-else-if="item.type === 'ferry'">
                                            <p class="mt-1 font-medium text-foreground">{{ item.ferryName }}</p>
                                            <p class="text-xs text-foreground-muted">
                                                {{ formatDateTime(item.departureDate, item.departureTime) }} ·
                                                {{ item.seatNumbers.length }} seat{{ item.seatNumbers.length === 1 ? '' : 's' }}
                                                <span v-if="item.paymentMethod === 'cash'"> · cash on board</span>
                                            </p>
                                        </template>
                                        <template v-else>
                                            <p class="mt-1 font-medium text-foreground">{{ item.eventName }}</p>
                                            <p class="text-xs text-foreground-muted">
                                                {{ formatDateTime(item.slotDate, item.slotTime) }} ·
                                                {{ item.ticketCount }} ticket{{ item.ticketCount === 1 ? '' : 's' }}
                                            </p>
                                        </template>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-3">
                                        <span class="font-medium text-foreground">{{ formatMoney(item.subtotal) }}</span>
                                        <button
                                            type="button"
                                            class="rounded p-1 text-foreground-muted transition-colors hover:bg-surface-hover hover:text-danger"
                                            :aria-label="`Remove ${item.hotelName || item.ferryName || item.eventName}`"
                                            @click="removeItem(item)"
                                        >
                                            <TIcon name="trash" :size="15" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <template #footer>
                                <div class="w-full">
                                    <div class="flex justify-between text-sm font-semibold">
                                        <span class="text-foreground">Total due now</span>
                                        <span class="text-foreground">{{ formatMoney(cart.onlineTotal) }}</span>
                                    </div>
                                    <p v-if="cart.cashDueTotal > 0" class="mt-1 text-xs text-foreground-muted">
                                        Plus {{ formatMoney(cart.cashDueTotal) }} cash due on board for ferry tickets.
                                    </p>
                                </div>
                            </template>
                        </TCard>

                        <TCard icon="card" title="Payment">
                            <PaymentForm
                                :total="cart.onlineTotal"
                                :processing="paying"
                                :error="payError"
                                :require-card="paymentRequired"
                                @submit="checkout"
                            >
                                <template #no-payment>
                                    <p class="text-sm text-foreground-muted">
                                        No online payment needed - everything in your itinerary is cash on board.
                                    </p>
                                </template>
                            </PaymentForm>
                        </TCard>
                    </template>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
