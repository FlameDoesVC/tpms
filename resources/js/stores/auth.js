import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loaded: false,
    }),

    getters: {
        isAuthenticated: (state) => !!state.user,
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

        setUser(user) {
            this.user = user;
            this.loaded = true;
        },

        clearUser() {
            this.user = null;
        },
    },
});
