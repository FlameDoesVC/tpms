import '../css/app.css';
import './bootstrap';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import routes from './routes';
import { useAuthStore } from '@/stores/auth';

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, from, next) => {
    const auth = useAuthStore();
    if (!auth.loaded) {
        await auth.fetchUser();
    }

    if (to.meta.auth && !auth.isAuthenticated) {
        return next({ name: 'login' });
    }
    if (to.meta.guest && auth.isAuthenticated) {
        return next({ name: 'dashboard' });
    }
    // Role checks only apply once a real session exists — anonymous visitors
    // may reach guestCheckout-allowed pages and become a visitor mid-flow.
    if (to.meta.roles && auth.isAuthenticated && !to.meta.roles.includes(auth.userRole)) {
        return next({ name: 'dashboard' });
    }
    next();
});

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#app');
