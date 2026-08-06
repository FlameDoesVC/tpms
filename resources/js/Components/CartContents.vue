<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCartStore } from '@/stores/cart';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import { useConfirm } from '@/composables/useConfirm';
import { formatDateRange, formatDateTime, formatMoney } from '@/utils/format';

defineEmits(['navigated']);

const route = useRoute();
const router = useRouter();
const cart = useCartStore();
const confirm = useConfirm();

const checkout = () => {
    router.push({ name: 'cart.checkout' });
};

// Points at whatever booking step would naturally come next, based on what's
// already in the cart. Ferry stays optional - a hotel item with no ferry still
// nudges toward booking one, but never blocks checkout.
//
// Suggestions for the page the visitor is already on are dropped: nudging
// someone to "book a theme park" while they're stood on the theme park page is
// noise. Candidates are ordered by usefulness so a filtered-out first choice
// falls through to the next real one rather than leaving no prompt at all.
const nextStep = computed(() => {
    const has = (type) => cart.items.some((item) => item.type === type);
    const candidates = [];

    if (cart.items.length === 0) {
        candidates.push(
            { linkText: 'Browse theme park events', name: 'themepark.home' },
            { linkText: 'Browse hotels', name: 'hotels.index' },
        );
    } else {
        if (has('hotel') && !has('ferry')) {
            candidates.push({ linkText: 'Book ferry seats for the trip', name: 'ferry.book' });
        }
        if (has('themepark') && !has('hotel')) {
            candidates.push({ linkText: 'Book a hotel for the stay?', name: 'hotels.index' });
        }
    }

    const pick = candidates.find((c) => c.name !== route.name);
    return pick ? { linkText: pick.linkText, to: { name: pick.name } } : null;
});

const updateTicketCount = (item, value) => {
    const ticketCount = Math.max(1, Math.round(value));
    cart.updateItem(item.id, { ticketCount, subtotal: ticketCount * item.pricePerTicket });
};

// A ferry ticket can't exist without the room it was booked against, so
// removing the room takes it too - warn rather than letting an item the user
// never touched silently disappear.
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
</script>

<template>
    <div class="flex-1 space-y-3 overflow-y-auto p-4">
        <p v-if="cart.items.length === 0" class="text-sm text-foreground-muted">
            Your itinerary is empty.
            <router-link
                v-if="nextStep"
                :to="nextStep.to"
                class="font-medium text-primary hover:underline"
            >
                {{ nextStep.linkText }}
            </router-link>
        </p>

        <div
            v-for="item in cart.items"
            :key="item.id"
            class="flex items-start justify-between gap-2 rounded-lg bg-surface-hover p-3 text-sm"
        >
            <div>
                <template v-if="item.type === 'hotel'">
                    <p class="font-medium text-foreground">{{ item.hotelName }} - {{ item.roomTypeName }}</p>
                    <p class="text-xs text-foreground-muted">
                        {{ item.quantity }} room{{ item.quantity === 1 ? '' : 's' }} ·
                        {{ formatDateRange(item.checkIn, item.checkOut) }}
                    </p>
                </template>
                <template v-else-if="item.type === 'ferry'">
                    <p class="font-medium text-foreground">{{ item.ferryName }}</p>
                    <p class="text-xs text-foreground-muted">
                        {{ formatDateTime(item.departureDate, item.departureTime) }} ·
                        {{ item.seatNumbers.length }} seat{{ item.seatNumbers.length === 1 ? '' : 's' }}
                        <span v-if="item.paymentMethod === 'cash'"> · cash on board</span>
                    </p>
                </template>
                <template v-else>
                    <p class="font-medium text-foreground">{{ item.eventName }}</p>
                    <p class="text-xs text-foreground-muted">{{ formatDateTime(item.slotDate, item.slotTime) }}</p>
                    <TNumberInput
                        label="Tickets"
                        :model-value="item.ticketCount"
                        @update:model-value="v => updateTicketCount(item, v)"
                        :min="1"
                        size="sm"
                        class="mt-1 w-24"
                    />
                </template>
                <p class="mt-1 text-xs font-medium text-foreground-secondary">{{ formatMoney(item.subtotal) }}</p>
            </div>
            <button
                type="button"
                class="shrink-0 rounded p-1 text-foreground-muted transition-colors hover:bg-surface hover:text-danger"
                :aria-label="`Remove ${item.hotelName || item.ferryName || item.eventName}`"
                @click="removeItem(item)"
            >
                <TIcon name="trash" :size="15" />
            </button>
        </div>

        <!-- Only for a non-empty itinerary; the empty state renders its own
             copy of this suggestion above. -->
        <p v-if="nextStep && cart.items.length" class="text-sm text-foreground-muted">
            <router-link :to="nextStep.to" class="font-medium text-primary hover:underline">{{ nextStep.linkText }}</router-link>
        </p>
    </div>

    <div v-if="cart.items.length" class="space-y-2 border-t p-4">
        <div class="flex justify-between text-sm font-semibold text-foreground">
            <span>Total due now</span>
            <span>{{ formatMoney(cart.onlineTotal) }}</span>
        </div>
        <p v-if="cart.cashDueTotal > 0" class="text-xs text-foreground-muted">
            Plus {{ formatMoney(cart.cashDueTotal) }} cash due on board for ferry tickets.
        </p>
        <TButton
            variant="primary"
            class="w-full"
            @click="checkout(); $emit('navigated')"
        >
            Checkout
        </TButton>
    </div>
</template>
