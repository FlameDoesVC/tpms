<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '@/stores/cart';
import TButton from '@/Components/ui/TButton.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';

defineEmits(['navigated']);

const router = useRouter();
const cart = useCartStore();

const checkout = () => {
    router.push({ name: 'cart.checkout' });
};

// Points at whatever booking step would naturally come next, based on what's
// already in the cart. Ferry stays optional - a hotel item with no ferry
// still nudges toward booking one, but never blocks checkout.
const nextStep = computed(() => {
    if (cart.items.length === 0) {
        return { prefix: 'Your cart is empty.', linkText: 'Book a theme park?', to: { name: 'themepark.home' } };
    }

    const hasThemepark = cart.items.some((item) => item.type === 'themepark');
    const hasHotel = cart.items.some((item) => item.type === 'hotel');
    const hasFerry = cart.items.some((item) => item.type === 'ferry');

    if (hasHotel && !hasFerry) {
        return { prefix: '', linkText: 'Book ferry seats for the trip', to: { name: 'ferry.book' } };
    }
    if (hasThemepark && !hasHotel) {
        return { prefix: '', linkText: 'Book a hotel for the stay?', to: { name: 'hotels.index' } };
    }
    return null;
});

const updateTicketCount = (item, value) => {
    const ticketCount = Math.max(1, Math.round(value));
    cart.updateItem(item.id, { ticketCount, subtotal: ticketCount * item.pricePerTicket });
};
</script>

<template>
    <div class="flex-1 space-y-3 overflow-y-auto p-4">
        <p v-if="cart.items.length === 0" class="text-sm text-foreground-muted">
            {{ nextStep.prefix }}
            <router-link :to="nextStep.to" class="font-medium text-primary hover:underline">{{ nextStep.linkText }}</router-link>
        </p>

        <div
            v-for="item in cart.items"
            :key="item.id"
            class="flex items-start justify-between gap-2 rounded-lg bg-surface-hover p-3 text-sm"
        >
            <div>
                <template v-if="item.type === 'hotel'">
                    <p class="font-medium text-foreground">{{ item.hotelName }} - {{ item.roomType }}</p>
                    <p class="text-xs text-foreground-muted">
                        {{ item.quantity }} room(s), {{ item.checkIn }} to {{ item.checkOut }}
                    </p>
                </template>
                <template v-else-if="item.type === 'ferry'">
                    <p class="font-medium text-foreground">{{ item.ferryName }}</p>
                    <p class="text-xs text-foreground-muted">
                        {{ item.departureDate }} at {{ item.departureTime }} - {{ item.seatNumbers.length }} seat(s)
                        ({{ item.paymentMethod === 'cash' ? 'cash on board' : 'online' }})
                    </p>
                </template>
                <template v-else>
                    <p class="font-medium text-foreground">{{ item.eventName }}</p>
                    <p class="text-xs text-foreground-muted">{{ item.slotDate }} at {{ item.slotTime }}</p>
                    <TNumberInput
                        label="Tickets"
                        :model-value="item.ticketCount"
                        @update:model-value="v => updateTicketCount(item, v)"
                        :min="1"
                        size="sm"
                        class="mt-1 w-24"
                    />
                </template>
                <p class="mt-1 text-xs font-medium text-foreground-secondary">${{ Number(item.subtotal).toFixed(2) }}</p>
            </div>
            <button type="button" @click="cart.removeItem(item.id)" class="text-xs text-danger hover:underline">
                Remove
            </button>
        </div>

        <p v-if="nextStep" class="text-sm text-foreground-muted">
            <router-link :to="nextStep.to" class="font-medium text-primary hover:underline">{{ nextStep.linkText }}</router-link>
        </p>
    </div>

    <div v-if="cart.items.length" class="space-y-2 border-t p-4">
        <div class="flex justify-between text-sm font-semibold text-foreground">
            <span>Total due now</span>
            <span>${{ cart.onlineTotal.toFixed(2) }}</span>
        </div>
        <p v-if="cart.cashDueTotal > 0" class="text-xs text-foreground-muted">
            Plus ${{ cart.cashDueTotal.toFixed(2) }} cash due on board for ferry tickets.
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
