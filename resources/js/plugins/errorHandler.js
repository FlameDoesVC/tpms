import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { showToast } from '@/composables/useToast';

export function setupErrorHandler(router) {
    axios.interceptors.response.use(
        (response) => response,
        (error) => {
            const status = error.response?.status;

            // /api/user is polled on every route (incl. public ones) to check
            // session state — a 401 there is expected for anonymous visitors,
            // not a session expiry, and fetchUser() already handles it itself.
            if (status === 401 && error.config?.url !== '/api/user') {
                useAuthStore().clearUser();
                // Best-effort lookups (e.g. smart-fill defaults on pages that
                // work fine for anonymous visitors) pass silent401 - a stale
                // or missing session there should just mean "nothing to
                // prefill", not yanking the visitor away to the login page.
                if (!error.config?.silent401 && router.currentRoute.value.name !== 'login') {
                    router.push({ name: 'login' });
                }
            } else if (status === 403) {
                router.push({ name: 'unauthorized' });
            } else if (status === 500) {
                showToast('Something went wrong. Please try again.');
            }

            return Promise.reject(error);
        },
    );
}
