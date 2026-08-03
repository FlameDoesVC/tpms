import { defineStore } from 'pinia';
import axios from 'axios';

const STORAGE_KEY = 'tpms.cart';

const loadFromStorage = () => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
};

const saveToStorage = (items) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
};

// Client-side only - nothing here is a real booking yet. Checkout walks
// through these items and fires the same create endpoints each module
// already had; nothing is committed until the visitor pays.
export const useCartStore = defineStore('cart', {
    state: () => ({
        items: loadFromStorage(),
    }),

    getters: {
        count: (state) => state.items.length,

        // Cash-on-board ferry items aren't paid here, so they're excluded
        // from the amount the checkout's card form charges.
        onlineTotal: (state) =>
            state.items
                .filter((item) => item.paymentMethod !== 'cash')
                .reduce((sum, item) => sum + Number(item.subtotal), 0),

        cashDueTotal: (state) =>
            state.items
                .filter((item) => item.paymentMethod === 'cash')
                .reduce((sum, item) => sum + Number(item.subtotal), 0),

        // Ferry tickets added against a hotel room that is still in the cart
        // can't outlive it (see removeItem). Exposed so the UI can warn before
        // a removal quietly takes a second item with it.
        dependentsOf: (state) => (id) =>
            state.items.filter((item) => item.type === 'ferry' && item.hotelCartItemId === id),
    },

    actions: {
        addItem(item) {
            this.items.push({ id: `${Date.now()}-${Math.random().toString(36).slice(2)}`, ...item });
            saveToStorage(this.items);
        },

        updateItem(id, changes) {
            const index = this.items.findIndex((item) => item.id === id);
            if (index === -1) return;
            this.items[index] = { ...this.items[index], ...changes };
            saveToStorage(this.items);
        },

        removeItem(id) {
            const removed = this.items.find((item) => item.id === id);
            this.items = this.items.filter((item) => item.id !== id);

            // A ferry ticket added against a still-in-cart hotel room has no
            // real booking to reference once that room is gone - it can't
            // stand on its own, so it's removed along with it.
            if (removed?.type === 'hotel') {
                this.items = this.items.filter((item) => !(item.type === 'ferry' && item.hotelCartItemId === id));
            }

            saveToStorage(this.items);
        },

        clear() {
            this.items = [];
            saveToStorage(this.items);
        },

        // Everything is created server-side in one DB transaction - either
        // all of it goes through, or none of it does, and the cart here is
        // only ever cleared once we know the whole thing actually succeeded.
        async checkout() {
            const { data } = await axios.post(
                '/api/cart/checkout',
                { items: this.items },
                { silent401: true }
            );
            return data;
        },
    },
});
