import { defineStore } from 'pinia';
import axios from 'axios';

export const useFerryStore = defineStore('ferry', {
    state: () => ({
        ferries: [],
        schedules: [],
        templates: [],
        myTickets: [],
        passengers: [],
        loading: {
            ferries: false,
            schedules: false,
            templates: false,
            tickets: false,
            purchasing: false,
            passengers: false,
        },
        error: {
            ferries: null,
            schedules: null,
            templates: null,
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
            this.error.tickets = null;
            try {
                const { data } = await axios.get('/api/ferry/tickets', { silent401: silent });
                this.myTickets = data;
            } catch (e) {
                // Without this the list page can't tell "request failed" from
                // "you own no tickets", and tells a paying customer the latter.
                this.error.tickets = e.response?.data?.message ?? 'Failed to load tickets.';
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

        async uncancelSchedule(id) {
            return this.updateSchedule(id, { status: 'scheduled' });
        },

        async lookupTicket(id) {
            const { data } = await axios.get(`/api/ferry/tickets/${id}`);
            return data;
        },

        // Scan resolution. The code is sent whole rather than having its digits
        // parsed into an id, because it no longer contains one.
        async lookupTicketByCode(code) {
            const { data } = await axios.get('/api/ferry/tickets/lookup', { params: { code } });
            return data;
        },

        async getPartyStatusByCode(code, scheduleId) {
            const { data } = await axios.get('/api/ferry/bookings/lookup', {
                params: { code, schedule_id: scheduleId },
            });
            return data;
        },

        // The departure is sent to the server, which is now the authority on
        // whether this ticket belongs to the boat being boarded and whether that
        // boat sails today. The gate screen still checks too, but only so the
        // operator sees the mismatch before pressing the button.
        async validateTicketOnSite(ticketOrId, scheduleId) {
            const id = typeof ticketOrId === 'object' ? ticketOrId.id : ticketOrId;
            const { data } = await axios.post(`/api/ferry/tickets/${id}/validate`, {
                schedule_id: scheduleId,
            });
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

        async fetchTemplates(ferryId) {
            this.loading.templates = true;
            this.error.templates = null;
            try {
                const { data } = await axios.get('/api/ferry/schedule-templates', { params: { ferry_id: ferryId } });
                this.templates = data;
            } catch (e) {
                this.error.templates = e.response?.data?.message ?? 'Failed to load recurring schedules.';
            } finally {
                this.loading.templates = false;
            }
        },

        async createTemplate(payload) {
            const { data } = await axios.post('/api/ferry/schedule-templates', payload);
            this.templates.push(data);
            await this.fetchSchedules();
            return data;
        },

        async updateTemplate(id, payload) {
            const { data } = await axios.patch(`/api/ferry/schedule-templates/${id}`, payload);
            const index = this.templates.findIndex((t) => t.id === id);
            if (index !== -1) this.templates[index] = data;
            await this.fetchSchedules();
            return data;
        },

        async stopTemplate(id) {
            await axios.delete(`/api/ferry/schedule-templates/${id}`);
            const index = this.templates.findIndex((t) => t.id === id);
            if (index !== -1) this.templates[index].is_active = false;
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
