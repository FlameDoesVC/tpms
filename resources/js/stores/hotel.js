import { defineStore } from 'pinia';
import axios from 'axios';

export const useHotelStore = defineStore('hotel', {
    state: () => ({
        hotels: [],
        popularHotels: [],
        hotel: null,
        rooms: [],
        myBookings: [],
        activeBooking: null,
        activeBookings: [],
        loading: {
            hotels: false,
            hotel: false,
            rooms: false,
            bookings: false,
            creatingBooking: false,
            activeBooking: false,
        },
        error: {
            hotels: null,
            hotel: null,
            rooms: null,
            bookings: null,
            creatingBooking: null,
            activeBooking: null,
        },
    }),

    actions: {
        async fetchPopularHotels() {
            const { data } = await axios.get('/api/hotels/popular');
            this.popularHotels = data;
        },

        // Every hotel, not just the first page. The browse page's ?hotel=<id>
        // deep link scrolls to a hotel's section, which only works if that
        // hotel is already rendered - so a "load more" control would leave
        // links to page-2 hotels silently broken. An island resort has tens of
        // hotels at most, so the page cap plus a follow-up loop is enough.
        // `all` opts into the management view, which includes deactivated hotels.
        // The server ignores it for anyone without a staff role, so this is a
        // request for the fuller list rather than a claim to be allowed it.
        async fetchHotels({ all = false } = {}) {
            this.loading.hotels = true;
            this.error.hotels = null;
            try {
                const scope = all ? { all: 1 } : {};
                const { data } = await axios.get('/api/hotels', {
                    params: { per_page: 100, ...scope },
                });
                let hotels = data.data;

                const lastPage = data.meta?.last_page ?? 1;
                for (let page = 2; page <= lastPage; page++) {
                    const { data: next } = await axios.get('/api/hotels', {
                        params: { per_page: 100, page, ...scope },
                    });
                    hotels = [...hotels, ...next.data];
                }

                this.hotels = hotels;
            } catch (e) {
                this.error.hotels = e.response?.data?.message ?? 'Failed to load hotels.';
            } finally {
                this.loading.hotels = false;
            }
        },

        async fetchHotel(hotelId) {
            this.loading.hotel = true;
            this.error.hotel = null;
            try {
                const { data } = await axios.get(`/api/hotels/${hotelId}`);
                this.hotel = data;
            } catch (e) {
                this.error.hotel = e.response?.data?.message ?? 'Failed to load hotel.';
            } finally {
                this.loading.hotel = false;
            }
        },

        async fetchRooms(hotelId, dates = {}) {
            this.loading.rooms = true;
            this.error.rooms = null;
            try {
                const { data } = await axios.get(`/api/hotels/${hotelId}/rooms`, {
                    params: {
                        check_in_date: dates.checkIn,
                        check_out_date: dates.checkOut,
                    },
                });
                this.rooms = data;
            } catch (e) {
                this.error.rooms = e.response?.data?.message ?? 'Failed to load rooms.';
            } finally {
                this.loading.rooms = false;
            }
        },

        // Side-effect-free: lets the combined hotel-booking page fetch room
        // types for many hotels at once without them overwriting each other.
        async fetchRoomTypes(hotelId, dates = {}) {
            const { data } = await axios.get(`/api/hotels/${hotelId}/room-types`, {
                params: {
                    check_in_date: dates.checkIn,
                    check_out_date: dates.checkOut,
                },
            });
            return data;
        },

        async fetchBooking(id) {
            this.loading.activeBooking = true;
            this.error.activeBooking = null;
            try {
                const { data } = await axios.get(`/api/bookings/${id}`);
                this.activeBooking = data;
            } catch (e) {
                this.error.activeBooking = e.response?.data?.message ?? 'Failed to load booking.';
            } finally {
                this.loading.activeBooking = false;
            }
        },

        // A single room-type purchase can create several bookings at once
        // (one party may need multiple rooms) - the confirmation page pays
        // for the whole group together.
        async fetchBookings(ids) {
            this.loading.activeBooking = true;
            this.error.activeBooking = null;
            try {
                this.activeBookings = await Promise.all(
                    ids.map((id) => axios.get(`/api/bookings/${id}`).then((r) => r.data))
                );
            } catch (e) {
                this.error.activeBooking = e.response?.data?.message ?? 'Failed to load booking.';
            } finally {
                this.loading.activeBooking = false;
            }
        },

        // Paying for a group of bookings. One request, not one per booking: the
        // server settles the whole stay in a single transaction, so a partial
        // failure can no longer leave half a party paid for.
        async payBookings(ids, { silent = false } = {}) {
            const { data } = await axios.post(
                '/api/bookings/pay',
                { booking_ids: ids },
                { silent401: silent }
            );
            data.forEach((booking) => this._syncBooking(booking));
            this.activeBookings = data;
            return data;
        },

        async createHotel(payload) {
            const { data } = await axios.post('/api/hotels', payload);
            this.hotels.unshift(data);
            return data;
        },

        // PHP never populates $_FILES for PATCH multipart bodies, so a file
        // upload has to travel as a spoofed POST (`_method=PATCH`) instead.
        async updateHotel(hotelId, payload) {
            const isUpload = payload instanceof FormData;
            if (isUpload) payload.append('_method', 'PATCH');

            const { data } = isUpload
                ? await axios.post(`/api/hotels/${hotelId}`, payload)
                : await axios.patch(`/api/hotels/${hotelId}`, payload);
            const index = this.hotels.findIndex((h) => h.id === hotelId);
            if (index !== -1) this.hotels[index] = data;
            return data;
        },

        async deleteHotel(hotelId) {
            await axios.delete(`/api/hotels/${hotelId}`);
            this.hotels = this.hotels.filter((h) => h.id !== hotelId);
        },

        async createRoom(hotelId, payload) {
            const { data } = await axios.post(`/api/hotels/${hotelId}/rooms`, payload);
            this.rooms.push(data);
            return data;
        },

        async updateRoom(roomId, payload) {
            const { data } = await axios.patch(`/api/rooms/${roomId}`, payload);
            const index = this.rooms.findIndex((r) => r.id === roomId);
            if (index !== -1) this.rooms[index] = data;
            return data;
        },

        async deleteRoom(roomId) {
            await axios.delete(`/api/rooms/${roomId}`);
            this.rooms = this.rooms.filter((r) => r.id !== roomId);
        },

        async createBooking(payload, { silent = false } = {}) {
            this.loading.creatingBooking = true;
            this.error.creatingBooking = null;
            try {
                const { data } = await axios.post('/api/bookings', payload, { silent401: silent });
                return data;
            } catch (e) {
                this.error.creatingBooking = e.response?.data?.errors
                    ?? e.response?.data?.message
                    ?? 'Failed to create booking.';
                throw e;
            } finally {
                this.loading.creatingBooking = false;
            }
        },

        async fetchMyBookings({ silent = false } = {}) {
            this.loading.bookings = true;
            this.error.bookings = null;
            try {
                const { data } = await axios.get('/api/bookings', { silent401: silent });
                this.myBookings = data.data;
            } catch (e) {
                this.error.bookings = e.response?.data?.message ?? 'Failed to load bookings.';
            } finally {
                this.loading.bookings = false;
            }
        },

        async cancelBooking(id) {
            const { data } = await axios.patch(`/api/bookings/${id}`, { status: 'cancelled' });
            this._syncBooking(data);
            return data;
        },

        // Front-desk staff marking a booking settled, e.g. cash taken at the
        // counter. Deliberately separate from payBookings: different actor,
        // different authorization, and only staff may write this status.
        async staffConfirmBooking(id) {
            const { data } = await axios.patch(`/api/bookings/${id}`, { status: 'confirmed' });
            this._syncBooking(data);
            return data;
        },

        _syncBooking(booking) {
            const index = this.myBookings.findIndex((b) => b.id === booking.id);
            if (index !== -1) this.myBookings[index] = booking;
            if (this.activeBooking?.id === booking.id) {
                this.activeBooking = { ...this.activeBooking, ...booking };
            }
        },
    },
});
