import '../css/app.css';
import './bootstrap';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import routes from './routes';
import { useAuthStore } from '@/stores/auth';
import { setupErrorHandler } from '@/plugins/errorHandler';

const router = createRouter({
    history: createWebHistory(),
    routes,
});

setupErrorHandler(router);

router.beforeEach(async (to, from, next) => {
    const auth = useAuthStore();
    if (!auth.loaded) {
        await auth.fetchUser();
    }

    if (to.meta.auth && !auth.isAuthenticated) {
        return next({ name: 'login' });
    }
    // A guest checkout session is still "authenticated" but hasn't claimed a
    // real account yet - it should still be able to reach login/register to
    // do that (merging its bookings into the account), unlike a real user.
    if (to.meta.guest && auth.isAuthenticated && !auth.isGuest) {
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
