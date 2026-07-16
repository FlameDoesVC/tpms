import { defineStore } from 'pinia';

export const useThemeParkStore = defineStore('themepark', {
    state: () => ({
        events: [],
        myBookings: [],
        capacityData: [],
        salesReport: [],
    }),
});
