<script setup>
import { computed, ref } from 'vue';
import TInput from '@/Components/ui/TInput.vue';
import { evaluatePassword, MIN_LENGTH } from '@/utils/passwordPolicy';

const props = defineProps({
    label: { type: String, default: 'Password' },
    error: { type: String, default: null },
    autocomplete: { type: String, default: 'new-password' },
    id: { type: String, default: null },
    required: { type: Boolean, default: false },
    autofocus: { type: Boolean, default: false },
    placeholder: { type: String, default: null },
    helper: { type: String, default: null },
    // Off for a current-password field, where a strength reading is noise: the
    // password already exists and the user cannot act on the feedback.
    showStrength: { type: Boolean, default: true },
    // Set on a confirmation field to get live match feedback instead of a
    // strength meter.
    confirms: { type: String, default: null },
});

const model = defineModel({ type: String, default: '' });

const revealed = ref(false);
const focused = ref(false);

const strength = computed(() => evaluatePassword(model.value));

const isConfirmField = computed(() => props.confirms !== null);

const confirmState = computed(() => {
    if (!isConfirmField.value || model.value.length === 0) return null;

    return model.value === props.confirms ? 'match' : 'mismatch';
});

// Guidance appears once there is something to say about it, and stays visible
// while the field is focused so it doesn't flicker away mid-typing.
const showGuidance = computed(() =>
    props.showStrength
    && !isConfirmField.value
    && (focused.value || model.value.length > 0)
);

// Callers focus the field after a failed submit; without this they would be
// focusing the wrapper.
const inputRef = ref(null);

defineExpose({
    focus: () => inputRef.value?.focus(),
    select: () => inputRef.value?.select(),
});

const BAR_TONES = {
    weak: 'bg-danger',
    fair: 'bg-warning',
    good: 'bg-primary',
    strong: 'bg-success',
};

const TEXT_TONES = {
    weak: 'text-danger',
    fair: 'text-warning',
    good: 'text-primary',
    strong: 'text-success',
};
</script>

<template>
    <div>
        <TInput
            ref="inputRef"
            :id="id"
            v-model="model"
            :type="revealed ? 'text' : 'password'"
            :label="label"
            :error="error"
            :helper="helper"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :required="required"
            :autofocus="autofocus"
            suffix-interactive
            @focus="focused = true"
            @blur="focused = false"
        >
            <template #suffix>
                <button
                    type="button"
                    class="-mr-1 rounded p-1 text-foreground-muted transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                    :aria-label="revealed ? 'Hide password' : 'Show password'"
                    :aria-pressed="revealed"
                    tabindex="0"
                    @click="revealed = !revealed"
                >
                    <!-- Inline rather than via TIcon: this is the only place in
                         the app that needs an eye glyph. -->
                    <svg
                        v-if="!revealed"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="h-4 w-4"
                        aria-hidden="true"
                    >
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg>
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.75"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="h-4 w-4"
                        aria-hidden="true"
                    >
                        <path d="M10.6 5.2A9.9 9.9 0 0 1 12 5c6.4 0 10 7 10 7a17.7 17.7 0 0 1-2.2 3.1M6.2 6.2A17.9 17.9 0 0 0 2 12s3.6 7 10 7a9.9 9.9 0 0 0 4.2-.9" />
                        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                        <path d="m3 3 18 18" />
                    </svg>
                </button>
            </template>
        </TInput>

        <!-- Strength guidance. Advisory: the only hard client-side rule is
             length, and the breach check can only happen server-side. -->
        <div v-if="showGuidance" class="mt-2">
            <div class="flex items-center gap-2">
                <div class="flex h-1 flex-1 gap-1" role="presentation">
                    <span
                        v-for="segment in 4"
                        :key="segment"
                        class="h-full flex-1 rounded-full transition-colors"
                        :class="segment <= strength.score ? BAR_TONES[strength.tone] : 'bg-surface-hover'"
                    />
                </div>
                <span
                    v-if="strength.label"
                    class="w-20 shrink-0 text-right text-xs font-medium"
                    :class="TEXT_TONES[strength.tone]"
                >
                    {{ strength.label }}
                </span>
            </div>

            <!-- aria-live so a screen reader hears the assessment change without
                 the field losing focus. -->
            <p class="mt-1.5 text-xs text-foreground-muted" aria-live="polite">
                <span v-if="strength.hint">{{ strength.hint }}</span>
                <span v-else-if="strength.empty">
                    At least {{ MIN_LENGTH }} characters. A short phrase works well.
                </span>
                <span v-else>
                    Checked against known data breaches when you save.
                </span>
            </p>
        </div>

        <!-- Confirmation feedback, so a typo is caught before submitting. -->
        <p
            v-if="isConfirmField && confirmState"
            class="mt-1.5 text-xs"
            :class="confirmState === 'match' ? 'text-success' : 'text-foreground-muted'"
            aria-live="polite"
        >
            {{ confirmState === 'match' ? 'Passwords match.' : "Passwords don't match yet." }}
        </p>
    </div>
</template>
