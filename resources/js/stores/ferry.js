import { defineStore } from 'pinia';
import axios from 'axios';

export const useFerryStore = defineStore('ferry', {
    state: () => ({
        ferries: [],
        schedules: [],
        myTickets: [],
        passengers: [],
        loading: {
            ferries: false,
            schedules: false,
            tickets: false,
            purchasing: false,
            passengers: false,
        },
        error: {
            ferries: null,
            schedules: null,
            tickets: null,
            purchasing: null,
            passengers: null,
        },
    }),

    actions: {
        async fetchFerries() {
            this.loading.ferries = true;
            try {
                const { data } = await axios.get('/api/ferries');
                this.ferries = data;
            } finally {
                this.loading.ferries = false;
            }
        },

        // Fetches schedules for a date without touching shared `schedules`
        // state - used where two dates (arrival/departure) are needed at once.
        async getSchedulesForDate(date) {
            const { data } = await axios.get('/api/ferry/schedules', { params: { date } });
            return data;
        },

        async fetchSchedules(date) {
            this.loading.schedules = true;
            this.error.schedules = null;
            try {
                const { data } = await axios.get('/api/ferry/schedules', { params: { date } });
                this.schedules = data;
            } catch (e) {
                this.error.schedules = e.response?.data?.message ?? 'Failed to load schedules.';
            } finally {
                this.loading.schedules = false;
            }
        },

        async getSeatMap(scheduleId) {
            const { data } = await axios.get(`/api/ferry/schedules/${scheduleId}/seats`);
            return data;
        },

        async purchaseTicket(scheduleId, bookingId, { seatNumbers, paymentMethod, silent = false }) {
            this.loading.purchasing = true;
            this.error.purchasing = null;
            try {
                const { data } = await axios.post('/api/ferry/tickets', {
                    schedule_id: scheduleId,
                    booking_id: bookingId,
                    seat_numbers: seatNumbers,
                    payment_method: paymentMethod,
                }, { silent401: silent });
                return data;
            } catch (e) {
                this.error.purchasing = e.response?.data?.errors ?? e.response?.data?.message ?? 'Purchase failed.';
                throw e;
            } finally {
                this.loading.purchasing = false;
            }
        },

        async fetchMyTickets({ silent = false } = {}) {
            this.loading.tickets = true;
            try {
                const { data } = await axios.get('/api/ferry/tickets', { silent401: silent });
                this.myTickets = data;
            } finally {
                this.loading.tickets = false;
            }
        },

        async createSchedule(payload) {
            const { data } = await axios.post('/api/ferry/schedules', payload);
            this.schedules.push(data);
            return data;
        },

        async updateSchedule(id, payload) {
            const { data } = await axios.patch(`/api/ferry/schedules/${id}`, payload);
            const index = this.schedules.findIndex((s) => s.id === id);
            if (index !== -1) this.schedules[index] = data;
            return data;
        },

        async cancelSchedule(id) {
            return this.updateSchedule(id, { status: 'cancelled' });
        },

        async lookupTicket(id) {
            const { data } = await axios.get(`/api/ferry/tickets/${id}`);
            return data;
        },

        async validateTicketOnSite(ticketOrId) {
            const id = typeof ticketOrId === 'object' ? ticketOrId.id : ticketOrId;
            const { data } = await axios.post(`/api/ferry/tickets/${id}/validate`);
            return data;
        },

        async cancelTicket(ticketOrId) {
            const id = typeof ticketOrId === 'object' ? ticketOrId.id : ticketOrId;
            const { data } = await axios.post(`/api/ferry/tickets/${id}/cancel`);
            return data;
        },

        async getPartyStatus(bookingId, scheduleId) {
            const { data } = await axios.get(`/api/ferry/bookings/${bookingId}/party`, {
                params: { schedule_id: scheduleId },
            });
            return data;
        },

        // Staff selling a walk-up ticket at the gate on a visitor's behalf -
        // the caller isn't the ticket's owner, so this can't reuse
        // purchaseTicket (which posts to the visitor self-service endpoint
        // and would fail its ownership check).
        async issueWalkupTicket(scheduleId, bookingId, { seatNumbers, paymentMethod }) {
            const { data } = await axios.post('/api/ferry/tickets/walkup', {
                schedule_id: scheduleId,
                booking_id: bookingId,
                seat_numbers: seatNumbers,
                payment_method: paymentMethod,
            });
            return data;
        },

        async fetchPassengers(scheduleId) {
            this.loading.passengers = true;
            try {
                const { data } = await axios.get(`/api/ferry/schedules/${scheduleId}/passengers`);
                this.passengers = data;
            } finally {
                this.loading.passengers = false;
            }
        },
    },
});
