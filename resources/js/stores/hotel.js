import { defineStore } from 'pinia';

export const useHotelStore = defineStore('hotel', {
    state: () => ({
        hotels: [],
        rooms: [],
        myBookings: [],
    }),
});
