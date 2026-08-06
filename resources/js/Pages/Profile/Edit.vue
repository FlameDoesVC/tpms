<script setup>
/**
 * Profile and account settings.
 *
 * History worth knowing: this was three identical bordered boxes, each with its
 * form clamped to max-w-xl inside a max-w-7xl container - a 576px ribbon of
 * content in a 1280px box. Widening the container alone didn't fix it, because
 * there was only ever one form's worth of content to show.
 *
 * So it is a settings surface now: a sticky sidebar picks a section, and each
 * section pairs its form with the context that belongs beside it. That uses the
 * width because there is genuinely more here, not because the box got bigger.
 *
 * Every control on this page is real. The theme selector writes the same
 * localStorage key the nav toggle does, and the resend button posts to
 * verification.send. Nothing here is a switch that looks settable and forgets.
 */
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { useRoute, useRouter } from 'vue-router';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TPageHeader from '@/Components/ui/TPageHeader.vue';
import TAvatar from '@/Components/ui/TAvatar.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { useAuthStore } from '@/stores/auth';
import { useTheme } from '@/composables/useTheme';
import { showToast } from '@/composables/useToast';
import { formatDate } from '@/utils/format';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const { isDark } = useTheme();

const SECTIONS = [
    { key: 'account', label: 'Account', icon: 'users', hint: 'Name and email' },
    { key: 'security', label: 'Security', icon: 'settings', hint: 'Password and verification' },
    { key: 'appearance', label: 'Appearance', icon: 'sun', hint: 'Light or dark' },
    { key: 'danger', label: 'Delete account', icon: 'trash', hint: 'Irreversible' },
];
/*
 * The open section lives in the URL (?section=security), not in component
 * state: a refresh keeps your place, and anything else in the app - a "verify
 * your email" nudge, a support link - can deep-link straight to a section.
 * replace() rather than push(), so flipping between sections doesn't bury the
 * back button.
 */
const isSection = (v) => SECTIONS.some((s) => s.key === v);
const active = ref(isSection(route.query.section) ? route.query.section : 'account');
watch(active, (key) => {
    router.replace({ query: { ...route.query, section: key === 'account' ? undefined : key } });
});

const roleLabel = computed(() => (auth.userRole ?? '').replace(/_/g, ' '));
const memberSince = computed(() =>
    auth.user?.created_at ? formatDate(auth.user.created_at, { weekday: false }) : null,
);
const emailVerified = computed(() => !!auth.user?.email_verified_at);

// isDark is a shared writable ref, so this is the same switch as the nav's.
const setTheme = (dark) => { isDark.value = dark; };

const sending = ref(false);
const resendVerification = async () => {
    sending.value = true;
    try {
        await axios.post('/email/verification-notification');
        showToast('Verification link sent — check your inbox.', 'success');
    } catch (e) {
        showToast(e.response?.data?.message ?? 'Could not send the link right now.');
    } finally {
        sending.value = false;
    }
};

const logout = async () => {
    await auth.logout();
    router.push({ name: 'login' });
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <TPageHeader title="Profile" />
        </template>

        <!-- No measure on the page: the sidebar plus content is what fills the
             shell, and each section manages its own column widths. -->
        <div class="shell pb-6 pt-5">
            <div class="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
                <!-- ------------------------------ sidebar ------------------------------ -->
                <aside class="space-y-4 lg:sticky lg:top-20 lg:self-start">
                    <div class="elevated rounded-xl border bg-surface p-4">
                        <div class="flex items-center gap-3">
                            <TAvatar :name="auth.user?.name ?? ''" size="lg" />
                            <div class="min-w-0">
                                <p class="truncate font-semibold tracking-tight text-foreground">
                                    {{ auth.user?.name }}
                                </p>
                                <p class="truncate text-xs text-foreground-muted">{{ auth.user?.email }}</p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <TBadge v-if="roleLabel" variant="primary">{{ roleLabel }}</TBadge>
                            <TBadge v-if="auth.isGuest" variant="warning">guest</TBadge>
                            <TBadge v-else-if="emailVerified" variant="success">verified</TBadge>
                            <TBadge v-else variant="warning">unverified</TBadge>
                        </div>

                        <!-- A guest has no password to change and can lose its
                             bookings, so this outranks everything else here. -->
                        <router-link
                            v-if="auth.isGuest"
                            :to="{ name: 'register' }"
                            class="mt-3 flex items-center justify-between rounded-lg bg-accent px-3 py-2 text-sm font-semibold text-accent-fg transition-colors hover:bg-accent-hover"
                        >
                            Claim this account
                            <TIcon name="arrowRight" :size="16" />
                        </router-link>
                    </div>

                    <!-- Same active treatment as the staff sidebar and the top
                         nav: soft fill plus primary ink. Vertical list at lg;
                         below that it becomes a horizontal chip row, so the
                         form isn't pushed two screens down on a phone. -->
                    <nav class="elevated hidden overflow-hidden rounded-xl border bg-surface p-2 lg:block">
                        <button
                            v-for="section in SECTIONS"
                            :key="section.key"
                            type="button"
                            class="flex w-full items-start gap-3 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition-colors"
                            :class="active === section.key
                                ? 'bg-primary-soft text-primary'
                                : 'text-foreground-secondary hover:bg-surface-hover hover:text-foreground'"
                            :aria-current="active === section.key ? 'true' : undefined"
                            @click="active = section.key"
                        >
                            <TIcon :name="section.icon" :size="17" class="mt-0.5 shrink-0" />
                            <span class="min-w-0">
                                <span class="block truncate">{{ section.label }}</span>
                                <span class="block truncate text-xs font-normal text-foreground-muted">
                                    {{ section.hint }}
                                </span>
                            </span>
                        </button>
                    </nav>

                    <nav class="flex gap-1.5 overflow-x-auto pb-1 lg:hidden" aria-label="Profile sections">
                        <button
                            v-for="section in SECTIONS"
                            :key="'m-' + section.key"
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition-colors"
                            :class="active === section.key
                                ? 'border-transparent bg-primary-soft text-primary'
                                : 'text-foreground-secondary hover:bg-surface-hover hover:text-foreground'"
                            :aria-current="active === section.key ? 'true' : undefined"
                            @click="active = section.key"
                        >
                            <TIcon :name="section.icon" :size="15" />
                            {{ section.label }}
                        </button>
                    </nav>

                    <button
                        type="button"
                        class="hidden w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-sm font-medium text-foreground-secondary transition-colors hover:bg-surface-hover hover:text-foreground lg:flex"
                        @click="logout"
                    >
                        <TIcon name="logout" :size="17" />
                        Sign out
                    </button>
                </aside>

                <!-- ------------------------------ content ------------------------------ -->
                <div class="min-w-0">
                    <!-- ACCOUNT -->
                    <section
                        v-if="active === 'account'"
                        class="elevated rounded-xl border bg-surface p-5 sm:p-6"
                    >
                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
                            <UpdateProfileInformationForm />

                            <dl class="space-y-3 rounded-lg bg-surface-sunken p-4 text-sm">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                        Member since
                                    </dt>
                                    <dd class="mt-0.5 font-medium text-foreground">{{ memberSince ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                        Role
                                    </dt>
                                    <dd class="mt-0.5 font-medium capitalize text-foreground">
                                        {{ roleLabel || '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                        Account type
                                    </dt>
                                    <dd class="mt-0.5 font-medium text-foreground">
                                        {{ auth.isGuest ? 'Guest checkout' : 'Registered' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    <!-- SECURITY -->
                    <section
                        v-else-if="active === 'security'"
                        class="elevated rounded-xl border bg-surface p-5 sm:p-6"
                    >
                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
                            <UpdatePasswordForm />

                            <div class="rounded-lg bg-surface-sunken p-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-foreground-muted">
                                    Email verification
                                </p>
                                <p
                                    class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium"
                                    :class="emailVerified ? 'text-success' : 'text-warning'"
                                >
                                    <TIcon :name="emailVerified ? 'checkCircle' : 'alert'" :size="15" />
                                    {{ emailVerified ? 'Verified' : 'Not verified yet' }}
                                </p>
                                <p v-if="!emailVerified" class="mt-2 text-sm text-foreground-secondary">
                                    Some actions stay locked until you confirm the address on your account.
                                </p>
                                <TButton
                                    v-if="!emailVerified"
                                    variant="secondary"
                                    size="sm"
                                    class="mt-3"
                                    :loading="sending"
                                    @click="resendVerification"
                                >
                                    Resend link
                                </TButton>
                            </div>
                        </div>
                    </section>

                    <!-- APPEARANCE -->
                    <section
                        v-else-if="active === 'appearance'"
                        class="elevated rounded-xl border bg-surface p-5 sm:p-6"
                    >
                        <h2 class="text-lg font-medium text-foreground">Appearance</h2>
                        <p class="mt-1 text-sm text-foreground-secondary">
                            Applies straight away and is remembered on this device.
                        </p>

                        <div class="mt-5 grid max-w-lg gap-3 sm:grid-cols-2">
                            <button
                                v-for="option in [
                                    { dark: false, label: 'Light', icon: 'sun' },
                                    { dark: true, label: 'Dark', icon: 'moon' },
                                ]"
                                :key="option.label"
                                type="button"
                                class="rounded-lg border p-4 text-left transition-colors"
                                :class="isDark === option.dark
                                    ? 'border-primary bg-primary-soft'
                                    : 'hover:bg-surface-hover'"
                                :aria-pressed="isDark === option.dark"
                                @click="setTheme(option.dark)"
                            >
                                <span
                                    class="inline-flex items-center gap-2 font-medium"
                                    :class="isDark === option.dark ? 'text-primary' : 'text-foreground'"
                                >
                                    <TIcon :name="option.icon" :size="17" />
                                    {{ option.label }}
                                    <TIcon v-if="isDark === option.dark" name="check" :size="15" />
                                </span>
                            </button>
                        </div>
                    </section>

                    <!-- DANGER -->
                    <section
                        v-else-if="active === 'danger'"
                        class="rounded-xl border border-danger/40 bg-danger-soft/50 p-5 sm:p-6"
                    >
                        <DeleteUserForm />
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
