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
        path: '/unauthorized',
        name: 'unauthorized',
        component: () => import('@/Pages/Unauthorized.vue'),
    },
    {
        path: '/profile',
        name: 'profile.edit',
        component: () => import('@/Pages/Profile/Edit.vue'),
        meta: { auth: true },
    },
    {
        // No auth required: guest checkout lets a visitor browse and book
        // before an account exists (see AutoLoginGuest on the backend).
        path: '/hotels',
        name: 'hotels.index',
        component: () => import('@/Pages/Visitor/HotelListView.vue'),
        meta: { roles: ['visitor'] },
        // ?hotel=<id> used to focus one hotel's section on the combined page.
        // Those links are in promotions, the homepage rails and the map, so they
        // are forwarded rather than broken.
        beforeEnter: (to) => {
            if (!to.query.hotel) return true;
            const { hotel, ...rest } = to.query;

            return { name: 'hotels.show', params: { id: hotel }, query: rest };
        },
    },
    {
        path: '/hotels/:id',
        name: 'hotels.show',
        component: () => import('@/Pages/Visitor/HotelDetailView.vue'),
        // Same guest-browsing rule as the listing: no auth, visitors only.
        meta: { roles: ['visitor'] },
    },
    {
        // ?ids=1,2,3 - a single room-type purchase can create several
        // bookings at once (a party needing multiple rooms); they're all
        // paid for together here.
        path: '/bookings/confirm',
        name: 'bookings.confirm',
        component: () => import('@/Pages/Visitor/BookingConfirmationView.vue'),
        meta: { roles: ['visitor'] },
    },
    {
        // Stays, ferry crossings and park tickets share one hub - they were
        // three separate pages, two of which had no navigation entry at all.
        path: '/trips',
        name: 'trips',
        component: () => import('@/Pages/Visitor/MyTripsView.vue'),
        meta: { auth: true, roles: ['visitor'] },
    },
    // Old bookmarks and any missed in-app link land on the matching tab.
    { path: '/bookings', redirect: () => ({ name: 'trips', query: { tab: 'hotel' } }) },
    {
        // No auth required: the cart itself is client-side, so an anonymous
        // guest-checkout visitor can review and pay for it same as any booking.
        path: '/checkout',
        name: 'cart.checkout',
        component: () => import('@/Pages/Visitor/CartCheckoutView.vue'),
        meta: { roles: ['visitor'] },
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
    {
        path: '/ferry/book',
        name: 'ferry.book',
        component: () => import('@/Pages/Visitor/FerryBookingView.vue'),
        meta: { roles: ['visitor'] },
    },
    { path: '/ferry/my-tickets', redirect: () => ({ name: 'trips', query: { tab: 'ferry' } }) },
    {
        path: '/ferry/schedules',
        name: 'ferry.schedule-management',
        component: () => import('@/Pages/Ferry/ScheduleManagementView.vue'),
        meta: { auth: true, roles: ['ferry_operator'] },
    },
    {
        path: '/ferry/fleet',
        name: 'ferry.fleet',
        component: () => import('@/Pages/Ferry/FleetManagementView.vue'),
        meta: { auth: true, roles: ['ferry_operator'] },
    },
    {
        path: '/ferry/validate',
        name: 'ferry.validate',
        component: () => import('@/Pages/Ferry/TicketValidationView.vue'),
        meta: { auth: true, roles: ['ferry_operator'] },
    },
    {
        path: '/ferry/passengers',
        name: 'ferry.passengers',
        component: () => import('@/Pages/Ferry/PassengerListView.vue'),
        meta: { auth: true, roles: ['ferry_operator'] },
    },
    {
        path: '/themepark',
        name: 'themepark.home',
        component: () => import('@/Pages/Visitor/ThemeParkHomeView.vue'),
        meta: { roles: ['visitor'] },
        // ?event=<id> used to filter this page down to one attraction. Those
        // links live in the homepage rails and the island map.
        beforeEnter: (to) => {
            if (!to.query.event) return true;
            const { event, ...rest } = to.query;

            return { name: 'themepark.event', params: { id: event }, query: rest };
        },
    },
    {
        // Declared before the /themepark/staff/* routes would matter either way -
        // these are literal segments, so there is no ambiguity with :id.
        path: '/themepark/events/:id',
        name: 'themepark.event',
        component: () => import('@/Pages/Visitor/EventDetailView.vue'),
        meta: { roles: ['visitor'] },
    },
    { path: '/themepark/my-bookings', redirect: () => ({ name: 'trips', query: { tab: 'park' } }) },
    {
        path: '/themepark/staff/events',
        name: 'themepark.event-management',
        component: () => import('@/Pages/ThemePark/EventManagementView.vue'),
        meta: { auth: true, roles: ['themepark_staff'] },
    },
    {
        path: '/themepark/staff/slots',
        name: 'themepark.slot-scheduling',
        component: () => import('@/Pages/ThemePark/SlotSchedulingView.vue'),
        meta: { auth: true, roles: ['themepark_staff'] },
    },
    {
        path: '/themepark/staff/capacity',
        name: 'themepark.capacity',
        component: () => import('@/Pages/ThemePark/CapacityDashboardView.vue'),
        meta: { auth: true, roles: ['themepark_staff'] },
    },
    {
        path: '/themepark/staff/validate',
        name: 'themepark.validate',
        component: () => import('@/Pages/ThemePark/TicketValidationView.vue'),
        meta: { auth: true, roles: ['themepark_staff'] },
    },
    {
        path: '/themepark/staff/walkin-sales',
        name: 'themepark.walkin-sales',
        component: () => import('@/Pages/ThemePark/WalkinSalesView.vue'),
        meta: { auth: true, roles: ['themepark_staff'] },
    },
    {
        path: '/themepark/staff/sales-report',
        name: 'themepark.sales-report',
        component: () => import('@/Pages/ThemePark/SalesReportView.vue'),
        meta: { auth: true, roles: ['themepark_staff'] },
    },
    {
        path: '/promotions',
        name: 'promotions',
        component: () => import('@/Pages/Shared/PromotionsView.vue'),
        meta: { auth: true, roles: ['hotel_manager', 'themepark_staff', 'ferry_operator', 'admin'] },
    },
    {
        path: '/admin/hotels',
        name: 'admin.hotels',
        component: () => import('@/Pages/Admin/HotelManagementView.vue'),
        meta: { auth: true, roles: ['admin'] },
    },
    {
        path: '/admin/map',
        name: 'admin.map',
        component: () => import('@/Pages/Admin/MapManagementView.vue'),
        meta: { auth: true, roles: ['admin'] },
    },
    {
        path: '/admin',
        name: 'admin.dashboard',
        component: () => import('@/Pages/Admin/DashboardView.vue'),
        meta: { auth: true, roles: ['admin'] },
    },
    {
        path: '/admin/users',
        name: 'admin.users',
        component: () => import('@/Pages/Admin/UserManagementView.vue'),
        meta: { auth: true, roles: ['admin'] },
    },
];

export default routes;
