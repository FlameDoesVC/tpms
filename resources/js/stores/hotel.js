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

        async fetchHotels() {
            this.loading.hotels = true;
            this.error.hotels = null;
            try {
                const { data } = await axios.get('/api/hotels');
                this.hotels = data.data;
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

        async createBooking(payload) {
            this.loading.creatingBooking = true;
            this.error.creatingBooking = null;
            try {
                const { data } = await axios.post('/api/bookings', payload);
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

        async fetchMyBookings() {
            this.loading.bookings = true;
            this.error.bookings = null;
            try {
                const { data } = await axios.get('/api/bookings');
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

        async confirmBooking(id) {
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
