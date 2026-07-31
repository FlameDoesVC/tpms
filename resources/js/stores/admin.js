import { defineStore } from 'pinia';
import axios from 'axios';

export const useAdminStore = defineStore('admin', {
    state: () => ({
        users: [],
        pagination: null,
        loading: false,
        error: null,
    }),

    actions: {
        async fetchUsers(page = 1) {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get('/api/admin/users', { params: { page } });
                this.users = data.data;
                this.pagination = data.meta;
            } catch (e) {
                this.error = e.response?.data?.message ?? 'Failed to load users.';
            } finally {
                this.loading = false;
            }
        },

        async createUser(payload) {
            const { data } = await axios.post('/api/admin/users', payload);
            this.users.unshift(data);
            return data;
        },

        async updateUser(id, payload) {
            const { data } = await axios.patch(`/api/admin/users/${id}`, payload);
            const index = this.users.findIndex((u) => u.id === id);
            if (index !== -1) this.users[index] = data;
            return data;
        },

        async deleteUser(id) {
            await axios.delete(`/api/admin/users/${id}`);
            this.users = this.users.filter((u) => u.id !== id);
        },
    },
});
