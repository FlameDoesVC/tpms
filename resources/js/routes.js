import GuestLayout from '@/Layouts/GuestLayout.vue';

const routes = [
    {
        path: '/',
        name: 'welcome',
        component: () => import('@/Pages/Welcome.vue'),
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/Pages/Auth/Login.vue'),
        meta: { guest: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('@/Pages/Auth/Register.vue'),
        meta: { guest: true },
    },
    {
        path: '/forgot-password',
        name: 'password.request',
        component: () => import('@/Pages/Auth/ForgotPassword.vue'),
        meta: { guest: true },
    },
    {
        path: '/reset-password/:token',
        name: 'password.reset',
        component: () => import('@/Pages/Auth/ResetPassword.vue'),
        meta: { guest: true },
    },
    {
        path: '/verify-email',
        name: 'verification.notice',
        component: () => import('@/Pages/Auth/VerifyEmail.vue'),
        meta: { auth: true },
    },
    {
        path: '/confirm-password',
        name: 'password.confirm',
        component: () => import('@/Pages/Auth/ConfirmPassword.vue'),
        meta: { auth: true },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: () => import('@/Pages/Dashboard.vue'),
        meta: { auth: true },
    },
    {
        path: '/profile',
        name: 'profile.edit',
        component: () => import('@/Pages/Profile/Edit.vue'),
        meta: { auth: true },
    },
];

export default routes;
