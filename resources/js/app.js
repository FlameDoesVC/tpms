import '../css/app.css';
import './bootstrap';

import { createApp } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import routes from './routes';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const router = createRouter({
    history: createWebHistory(),
    routes,
});

createApp(App).use(router).mount('#app')