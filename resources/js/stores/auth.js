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
