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

    // Browsing a list and opening a result is now two pages rather than one
    // expanding card, so navigation has to move the viewport: without this, the
    // detail page opens at whatever scroll offset the list was left at.
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition;
        // Clears the sticky header, which the in-page anchors would otherwise
        // land underneath.
        if (to.hash) return { el: to.hash, top: 88, behavior: 'smooth' };

        // Same page, only the query changed. The detail pages keep their dates
        // and guest count in the URL so a link is shareable, which means every
        // tap of a stepper is technically a navigation - scrolling to the top
        // for those threw the reader out of the room list mid-edit.
        if (to.path === from.path) return false;

        return { top: 0 };
    },
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
    // Admin bypasses all role restrictions and can access every page.
    if (to.meta.roles && auth.isAuthenticated && auth.userRole !== 'admin' && !to.meta.roles.includes(auth.userRole)) {
        return next({ name: 'dashboard' });
    }
    next();
});

const app = createApp(App);
app.use(createPinia());
app.use(router);
app.mount('#app');
