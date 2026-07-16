import { defineStore } from 'pinia';

export const useFerryStore = defineStore('ferry', {
    state: () => ({
        schedules: [],
        myTickets: [],
    }),
});
