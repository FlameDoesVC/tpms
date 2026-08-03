import { defineStore } from 'pinia';
import axios from 'axios';

export const useThemeParkStore = defineStore('themepark', {
    state: () => ({
        events: [],
        popularEvents: [],
        event: null,
        slots: [],
        templates: [],
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
            templates: false,
        },
        error: {
            events: null,
            event: null,
            booking: null,
            bookings: null,
            templates: null,
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

        // Side-effect-free: lets the combined theme-park page fetch slots for
        // many events at once without them overwriting shared `slots` state.
        async fetchSlotsForEvent(eventId, date) {
            const { data } = await axios.get(`/api/themepark/events/${eventId}/slots`, { params: { date } });
            return data;
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

        async bookSlot(slotId, ticketCount, { silent = false } = {}) {
            this.loading.booking = true;
            this.error.booking = null;
            try {
                const { data } = await axios.post('/api/themepark/bookings', {
                    event_slot_id: slotId,
                    ticket_count: ticketCount,
                }, { silent401: silent });
                return data;
            } catch (e) {
                this.error.booking = e.response?.data?.errors ?? e.response?.data?.message ?? 'Booking failed.';
                throw e;
            } finally {
                this.loading.booking = false;
            }
        },

        async fetchMyBookings({ silent = false } = {}) {
            this.loading.bookings = true;
            this.error.bookings = null;
            try {
                const { data } = await axios.get('/api/themepark/bookings', { silent401: silent });
                this.myBookings = data;
            } catch (e) {
                // Without this the list page can't tell "request failed" from
                // "you own no bookings", and tells a paying customer the latter.
                this.error.bookings = e.response?.data?.message ?? 'Failed to load bookings.';
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

        // All events' slots together, for the staff scheduling calendar.
        async fetchAllSlots() {
            this.loading.event = true;
            try {
                const { data } = await axios.get('/api/themepark/slots');
                this.slots = data;
            } finally {
                this.loading.event = false;
            }
        },

        async updateSlot(id, payload) {
            const { data } = await axios.patch(`/api/themepark/slots/${id}`, payload);
            const index = this.slots.findIndex((s) => s.id === id);
            if (index !== -1) this.slots[index] = data;
            return data;
        },

        async cancelSlot(id) {
            return this.updateSlot(id, { status: 'cancelled' });
        },

        async uncancelSlot(id) {
            return this.updateSlot(id, { status: 'scheduled' });
        },

        async fetchTemplates(eventId) {
            this.loading.templates = true;
            this.error.templates = null;
            try {
                const { data } = await axios.get('/api/themepark/slot-templates', { params: { event_id: eventId } });
                this.templates = data;
            } catch (e) {
                this.error.templates = e.response?.data?.message ?? 'Failed to load recurring schedules.';
            } finally {
                this.loading.templates = false;
            }
        },

        async createTemplate(payload) {
            const { data } = await axios.post('/api/themepark/slot-templates', payload);
            this.templates.push(data);
            return data;
        },

        async updateTemplate(id, payload) {
            const { data } = await axios.patch(`/api/themepark/slot-templates/${id}`, payload);
            const index = this.templates.findIndex((t) => t.id === id);
            if (index !== -1) this.templates[index] = data;
            return data;
        },

        async stopTemplate(id) {
            await axios.delete(`/api/themepark/slot-templates/${id}`);
            const index = this.templates.findIndex((t) => t.id === id);
            if (index !== -1) this.templates[index].is_active = false;
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
