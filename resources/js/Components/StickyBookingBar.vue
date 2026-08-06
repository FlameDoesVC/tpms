<script setup>
/**
 * The band at the top of a detail page holding the parameters the whole page
 * is priced against.
 *
 * Styled as an action, not a filter: it uses the accent surface and states the
 * consequence, because changing a date here re-prices and re-checks every room
 * or slot below it. Lifted out of the old combined booking pages so the hotel
 * and attraction pages cannot drift apart.
 */
import TIcon from '@/Components/ui/TIcon.vue';

defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: '' },
    icon: { type: String, default: 'calendar' },
});
</script>

<template>
    <div class="elevated sticky top-[4.25rem] z-20 rounded-xl border border-primary/25 bg-primary-soft">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 p-4">
            <div class="min-w-[14rem] flex-1">
                <p class="inline-flex items-center gap-2 text-sm font-semibold text-primary">
                    <TIcon :name="icon" :size="16" />
                    {{ title }}
                </p>
                <p v-if="hint" class="mt-0.5 text-xs text-foreground-secondary">{{ hint }}</p>
            </div>

            <!-- Stepper and chip columns stay `auto` in the caller's grid: a
                 stepper stretched across a wide column splits its -/+ apart. -->
            <slot />
        </div>
    </div>
</template>
