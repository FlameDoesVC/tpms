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
    {
        path: '/hotels',
        name: 'hotels.index',
        component: () => import('@/Pages/Visitor/HotelListView.vue'),
        meta: { auth: true, roles: ['visitor'] },
    },
    {
        path: '/hotels/:id',
        name: 'hotels.show',
        component: () => import('@/Pages/Visitor/HotelDetailView.vue'),
        meta: { auth: true, roles: ['visitor'] },
    },
    {
        path: '/bookings/:id/confirm',
        name: 'bookings.confirm',
        component: () => import('@/Pages/Visitor/BookingConfirmationView.vue'),
        meta: { auth: true, roles: ['visitor'] },
    },
    {
        path: '/manager/hotel-dashboard',
        name: 'manager.hotel-dashboard',
        component: () => import('@/Pages/Manager/HotelDashboardView.vue'),
        meta: { auth: true, roles: ['hotel_manager'] },
    },
    {
        path: '/manager/rooms',
        name: 'manager.rooms',
        component: () => import('@/Pages/Manager/RoomManagementView.vue'),
        meta: { auth: true, roles: ['hotel_manager'] },
    },
];

export default routes;
