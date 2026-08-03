import { defineStore } from 'pinia';
import axios from 'axios';

export const usePromotionsStore = defineStore('promotions', {
    state: () => ({
        active: [],
        managed: [],
        loading: false,
        error: null,
    }),

    actions: {
        async fetchActive() {
            const { data } = await axios.get('/api/promotions');
            this.active = data;
        },

        async fetchManaged() {
            this.loading = true;
            this.error = null;
            try {
                const { data } = await axios.get('/api/promotions/manage');
                this.managed = data;
            } catch (e) {
                this.error = e.response?.data?.message ?? 'Failed to load promotions.';
            } finally {
                this.loading = false;
            }
        },

        async create(payload) {
            const { data } = await axios.post('/api/promotions', payload);
            this.managed.unshift(data);
            return data;
        },

        // PHP never populates $_FILES for PATCH multipart bodies, so a file
        // upload has to travel as a spoofed POST (`_method=PATCH`) instead.
        async update(id, payload) {
            const isUpload = payload instanceof FormData;
            if (isUpload) payload.append('_method', 'PATCH');

            const { data } = isUpload
                ? await axios.post(`/api/promotions/${id}`, payload)
                : await axios.patch(`/api/promotions/${id}`, payload);
            const i = this.managed.findIndex((p) => p.id === id);
            if (i !== -1) this.managed[i] = data;
            return data;
        },

        async remove(id) {
            await axios.delete(`/api/promotions/${id}`);
            this.managed = this.managed.filter((p) => p.id !== id);
        },
    },
});
