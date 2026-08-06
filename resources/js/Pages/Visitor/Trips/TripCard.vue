<script setup>
/**
 * One trip: a stay, with the crossings and park time that belong to it, in the
 * order they happen.
 *
 * This is the shape the rest of the product already promises - the landing page
 * literally says "One trip, three legs - chosen together, paid together" - and
 * the trips page was the one place that broke it apart again, into three lists
 * you had to rejoin by reading dates.
 *
 * The legs are assembled two different ways, and the difference matters:
 * crossings attach to a stay by a real foreign key (ferry_tickets.booking_id, set
 * because the server refuses a seat without a stay), while park slots have no
 * such column and are attached by falling inside the stay's dates. The first is
 * a fact; the second is an inference, which is why an out-of-range park booking
 * is shown separately rather than forced into a trip it might not belong to.
 */
import { computed } from 'vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TIcon from '@/Components/ui/TIcon.vue';
import { formatDate, formatDateRange, formatMoney, formatTime, nightsBetween } from '@/utils/format';

const props = defineProps({
    stay: { type: Object, required: true },
    legs: { type: Array, default: () => [] },
    /** Upcoming trips are actionable; past and cancelled ones are a record. */
    tense: { type: String, default: 'upcoming' },
});

const STATUS_VARIANT = {
    confirmed: 'success',
    issued: 'success',
    pending: 'warning',
    used: 'neutral',
    cancelled: 'neutral',
};
const variantFor = (status) => STATUS_VARIANT[status] ?? 'neutral';

const nights = computed(() => nightsBetween(props.stay.check_in_date, props.stay.check_out_date));
const guests = computed(() => props.stay.party_guests_count ?? props.stay.guests_count);

// Everything the trip costs, not just the room - the number a visitor actually
// cares about was previously split across three tabs.
const total = computed(() => {
    const stay = Number(props.stay.total_price) || 0;
    return props.legs.reduce((sum, leg) => sum + (Number(leg.price) || 0), stay);
});

const isCancelled = computed(() => props.stay.status === 'cancelled');
const awaitingPayment = computed(() => props.stay.status === 'pending');

// A stay with no crossings is the gap worth surfacing: the server enforces that
// a ferry seat is booked against a stay, so this is the next step, not a nag.
const hasCrossings = computed(() => props.legs.some((leg) => leg.kind === 'ferry'));
const hasParkTime = computed(() => props.legs.some((leg) => leg.kind === 'park'));
</script>

<template>
    <article
        class="elevated overflow-hidden rounded-xl border bg-surface"
        :class="isCancelled ? 'opacity-70' : ''"
    >
        <!-- The stay is the spine of the trip, so it heads the card. -->
        <header class="flex flex-wrap items-start justify-between gap-4 border-b p-4 sm:p-5">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-lg font-semibold tracking-tight text-foreground">
                        {{ stay.room?.hotel?.name ?? 'Stay' }}
                    </h3>
                    <TBadge :variant="variantFor(stay.status)">{{ stay.status }}</TBadge>
                </div>
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-foreground-secondary">
                    <span class="inline-flex items-center gap-1.5 font-medium text-foreground">
                        <TIcon name="calendar" :size="14" />
                        {{ formatDateRange(stay.check_in_date, stay.check_out_date) }}
                    </span>
                    <span>{{ nights }} night{{ nights === 1 ? '' : 's' }}</span>
                    <span class="inline-flex items-center gap-1">
                        <TIcon name="users" :size="13" />
                        {{ guests }} guest{{ guests === 1 ? '' : 's' }}
                    </span>
                    <span v-if="stay.room?.room_type?.name">{{ stay.room.room_type.name }}</span>
                </p>
            </div>

            <div class="text-right">
                <p class="text-lg font-semibold tabular-nums text-foreground">{{ formatMoney(total) }}</p>
                <p class="text-xs text-foreground-muted">trip total</p>
            </div>
        </header>

        <!-- Legs as tiles flowing across the card, in the order they happen.
             They were stacked full-width rows, which read fine at 900px and
             became stretched banners at 1400 - the system convention is that
             pages run the full screen width, so the card interior has to be
             the thing that scales. Chronology still reads left to right. -->
        <ol v-if="legs.length" class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 2xl:grid-cols-3">
            <li
                v-for="leg in legs"
                :key="leg.key"
                class="flex flex-col gap-2 rounded-lg border bg-surface-sunken/60 p-3"
            >
                <div class="flex items-center gap-2.5">
                    <span
                        class="grid h-8 w-8 shrink-0 place-items-center rounded-full"
                        :class="leg.kind === 'ferry' ? 'bg-accent/10 text-accent' : 'bg-primary-soft text-primary'"
                    >
                        <TIcon :name="leg.kind === 'ferry' ? 'ferry' : 'sparkle'" :size="15" />
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-foreground">{{ leg.title }}</span>
                    <TBadge :variant="variantFor(leg.status)">{{ leg.status }}</TBadge>
                </div>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-foreground-muted">
                    <span class="inline-flex items-center gap-1">
                        <TIcon name="clock" :size="12" />
                        {{ formatDate(leg.date, { weekday: true }) }}<template v-if="leg.time"> · {{ formatTime(leg.time) }}</template>
                    </span>
                    <span v-if="leg.detail">{{ leg.detail }}</span>
                </div>

                <div class="mt-auto flex items-center justify-between gap-2">
                    <span class="truncate font-mono text-[11px] text-foreground-muted">{{ leg.reference }}</span>
                    <span class="text-sm font-medium tabular-nums text-foreground-secondary">
                        {{ leg.price ? formatMoney(leg.price) : '—' }}
                    </span>
                </div>
            </li>
        </ol>

        <!-- What's missing from the trip, and what to do about it. Only worth
             saying while the trip can still be changed. -->
        <footer class="flex flex-wrap items-center justify-between gap-3 border-t bg-surface-sunken px-4 py-3 sm:px-5">
            <p class="font-mono text-xs text-foreground-muted">{{ stay.reference_code }}</p>

            <div v-if="tense === 'upcoming' && !isCancelled" class="flex flex-wrap items-center gap-3">
                <router-link
                    v-if="awaitingPayment"
                    :to="{ name: 'bookings.confirm', query: { ids: stay.id } }"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-accent px-3 py-1.5 text-sm font-semibold text-accent-fg transition-colors hover:bg-accent-hover"
                >
                    <TIcon name="card" :size="15" />
                    Pay now
                </router-link>

                <router-link
                    v-if="!hasCrossings"
                    :to="{ name: 'ferry.book' }"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                >
                    <TIcon name="ferry" :size="15" />
                    Book ferry seats
                </router-link>

                <router-link
                    v-if="!hasParkTime"
                    :to="{ name: 'themepark.home' }"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-foreground-secondary hover:text-foreground hover:underline"
                >
                    <TIcon name="sparkle" :size="15" />
                    Add park time
                </router-link>
            </div>
        </footer>
    </article>
</template>
