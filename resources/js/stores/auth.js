import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loaded: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
        // The app is built around one role per account, so this reads the first.
        // Anything that must hold for a user carrying SEVERAL roles should use
        // hasRole/isAdmin below instead, which look at all of them - otherwise
        // whichever role the relation happens to return first decides.
        userRole: (state) => state.user?.roles?.[0]?.name ?? null,
        isGuest: (state) => !!state.user?.is_guest,

        roleNames: (state) => (state.user?.roles ?? []).map((role) => role.name),
        /** True if the account carries this role, whatever order it comes back in. */
        hasRole: (state) => (name) => (state.user?.roles ?? []).some((role) => role.name === name),
        isAdmin() {
            return this.hasRole('admin');
        },
        /**
         * May use the customer-facing surfaces: browse, cart, checkout, trips.
         *
         * Admin is included deliberately. The router already waves admin through
         * every route, so gating these on `userRole === 'visitor'` meant an admin
         * could open /hotels but had no tabs, no cart and no way back to their
         * itinerary. Anonymous visitors count too - guest checkout lets them shop
         * before an account exists.
         */
        canShop() {
            return !this.isAuthenticated || this.hasRole('visitor') || this.isAdmin;
        },
    },

    actions: {
        async fetchUser() {
            try {
                const { data } = await axios.get('/api/user');
                this.user = data;
            } catch {
                this.user = null;
            }
            this.loaded = true;
        },

        async logout() {
            await axios.post('/logout');
            this.user = null;
        },

        setUser(user) {
            this.user = user;
            this.loaded = true;
        },

        clearUser() {
            this.user = null;
        },
    },
});
