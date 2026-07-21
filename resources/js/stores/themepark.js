import { defineStore } from 'pinia';
import axios from 'axios';

export const useThemeParkStore = defineStore('themepark', {
    state: () => ({
        events: [],
        popularEvents: [],
        event: null,
        slots: [],
        myBookings: [],
        capacityData: [],
        salesReport: [],
        loading: {
            events: false,
            event: false,
            booking: false,
            bookings: false,
            capacity: false,
            sales: false,
        },
        error: {
            events: null,
            event: null,
            booking: null,
            bookings: null,
        },
    }),

    actions: {
        async fetchPopularEvents() {
            const { data } = await axios.get('/api/themepark/events/popular');
            this.popularEvents = data;
        },

        async fetchEvents() {
            this.loading.events = true;
            try {
                const { data } = await axios.get('/api/themepark/events');
                this.events = data;
            } finally {
                this.loading.events = false;
            }
        },

        async fetchEventSlots(eventId, date) {
            this.loading.event = true;
            this.error.event = null;
            try {
                const { data } = await axios.get(`/api/themepark/events/${eventId}`, { params: { date } });
                this.event = data;
                this.slots = data.slots ?? [];
            } catch (e) {
                this.error.event = e.response?.data?.message ?? 'Failed to load event.';
            } finally {
                this.loading.event = false;
            }
        },

        async bookSlot(slotId, ticketCount) {
            this.loading.booking = true;
            this.error.booking = null;
            try {
                const { data } = await axios.post('/api/themepark/bookings', {
                    event_slot_id: slotId,
                    ticket_count: ticketCount,
                });
                return data;
            } catch (e) {
                this.error.booking = e.response?.data?.errors ?? e.response?.data?.message ?? 'Booking failed.';
                throw e;
            } finally {
                this.loading.booking = false;
            }
        },

        async fetchMyBookings() {
            this.loading.bookings = true;
            try {
                const { data } = await axios.get('/api/themepark/bookings');
                this.myBookings = data;
            } finally {
                this.loading.bookings = false;
            }
        },

        async cancelBooking(id) {
            const { data } = await axios.delete(`/api/themepark/bookings/${id}`);
            const index = this.myBookings.findIndex((b) => b.id === id);
            if (index !== -1) this.myBookings[index] = data;
            return data;
        },

        async createEvent(payload) {
            const { data } = await axios.post('/api/themepark/events', payload);
            this.events.push(data);
            return data;
        },

        async updateEvent(id, payload) {
            const { data } = await axios.patch(`/api/themepark/events/${id}`, payload);
            const index = this.events.findIndex((e) => e.id === id);
            if (index !== -1) this.events[index] = data;
            return data;
        },

        async deleteEvent(id) {
            await axios.delete(`/api/themepark/events/${id}`);
            this.events = this.events.filter((e) => e.id !== id);
        },

        async createSlot(eventId, payload) {
            const { data } = await axios.post(`/api/themepark/events/${eventId}/slots`, payload);
            this.slots.push(data);
            return data;
        },

        async fetchSlotsForDate(eventId, date) {
            const { data } = await axios.get(`/api/themepark/events/${eventId}/slots`, { params: { date } });
            this.slots = data;
            return data;
        },

        async sellTicket(payload) {
            const { data } = await axios.post('/api/themepark/tickets/sell', payload);
            return data;
        },

        async lookupTicket(bookingId) {
            const { data } = await axios.get(`/api/themepark/tickets/${bookingId}`);
            return data;
        },

        async validateTicket(bookingId) {
            const { data } = await axios.post(`/api/themepark/tickets/${bookingId}/validate`);
            return data;
        },

        async fetchCapacity() {
            this.loading.capacity = true;
            try {
                const { data } = await axios.get('/api/themepark/capacity');
                this.capacityData = data;
            } finally {
                this.loading.capacity = false;
            }
        },

        async fetchSalesReport(date) {
            this.loading.sales = true;
            try {
                const { data } = await axios.get('/api/themepark/reports/sales', { params: { date } });
                this.salesReport = data;
            } finally {
                this.loading.sales = false;
            }
        },
    },
});
