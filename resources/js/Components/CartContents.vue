<script setup>
import { useRouter } from 'vue-router';
import { useCartStore } from '@/stores/cart';

defineEmits(['navigated']);

const router = useRouter();
const cart = useCartStore();

const checkout = () => {
    router.push({ name: 'cart.checkout' });
};

const updateTicketCount = (item, value) => {
    const ticketCount = Math.max(1, Math.round(value));
    cart.updateItem(item.id, { ticketCount, subtotal: ticketCount * item.pricePerTicket });
};
</script>

<template>
    <div class="flex-1 space-y-3 overflow-y-auto p-4">
        <p v-if="cart.items.length === 0" class="text-sm text-gray-500">Your cart is empty.</p>

        <div
            v-for="item in cart.items"
            :key="item.id"
            class="flex items-start justify-between gap-2 rounded-lg bg-gray-50 p-3 text-sm"
        >
            <div>
                <template v-if="item.type === 'hotel'">
                    <p class="font-medium text-gray-900">{{ item.hotelName }} - {{ item.roomType }}</p>
                    <p class="text-xs text-gray-500">
                        {{ item.quantity }} room(s), {{ item.checkIn }} to {{ item.checkOut }}
                    </p>
                </template>
                <template v-else-if="item.type === 'ferry'">
                    <p class="font-medium text-gray-900">{{ item.ferryName }}</p>
                    <p class="text-xs text-gray-500">
                        {{ item.departureDate }} at {{ item.departureTime }} - {{ item.seatNumbers.length }} seat(s)
                        ({{ item.paymentMethod === 'cash' ? 'cash on board' : 'online' }})
                    </p>
                </template>
                <template v-else>
                    <p class="font-medium text-gray-900">{{ item.eventName }}</p>
                    <p class="text-xs text-gray-500">{{ item.slotDate }} at {{ item.slotTime }}</p>
                    <label class="mt-1 flex items-center gap-2 text-xs text-gray-600">
                        Tickets
                        <input
                            type="number"
                            min="1"
                            :value="item.ticketCount"
                            @input="updateTicketCount(item, Number($event.target.value))"
                            class="w-14 rounded-md border-gray-300 py-0.5 text-xs shadow-sm"
                        />
                    </label>
                </template>
                <p class="mt-1 text-xs font-medium text-gray-700">${{ Number(item.subtotal).toFixed(2) }}</p>
            </div>
            <button type="button" @click="cart.removeItem(item.id)" class="text-xs text-red-600 hover:underline">
                Remove
            </button>
        </div>
    </div>

    <div v-if="cart.items.length" class="space-y-2 border-t border-gray-200 p-4">
        <div class="flex justify-between text-sm font-semibold text-gray-900">
            <span>Total due now</span>
            <span>${{ cart.onlineTotal.toFixed(2) }}</span>
        </div>
        <p v-if="cart.cashDueTotal > 0" class="text-xs text-gray-500">
            Plus ${{ cart.cashDueTotal.toFixed(2) }} cash due on board for ferry tickets.
        </p>
        <button
            type="button"
            @click="checkout(); $emit('navigated')"
            class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
        >
            Checkout
        </button>
    </div>
</template>
