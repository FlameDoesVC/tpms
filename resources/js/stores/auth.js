import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loaded: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
        userRole: (state) => state.user?.roles?.[0]?.name ?? null,
        isGuest: (state) => !!state.user?.is_guest,
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

        async claimAccount(payload) {
            const { data } = await axios.patch('/api/guest/claim', payload);
            this.user = data.user;
            return data.user;
        },

        async loginAndMergeGuest(payload) {
            const { data } = await axios.post('/api/guest/login', payload);
            this.user = data.user;
            return data.user;
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
