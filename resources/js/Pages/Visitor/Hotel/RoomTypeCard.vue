<script setup>
/**
 * One bookable room type on a hotel's page: photographs, what the room is, what
 * it costs for the chosen stay, and the control that puts it in the itinerary.
 *
 * The selection rules are unchanged from the combined booking page - capacity
 * accumulates across every room type chosen for the same stay, so a party can
 * be split - they have only moved next to a picture of the room.
 */
import { computed } from 'vue';
import FacilityList from '@/Components/FacilityList.vue';
import ImageGallery from '@/Components/ImageGallery.vue';
import TBadge from '@/Components/ui/TBadge.vue';
import TButton from '@/Components/ui/TButton.vue';
import TNumberInput from '@/Components/ui/TNumberInput.vue';
import { formatMoney } from '@/utils/format';

const props = defineProps({
    roomType: { type: Object, required: true },
    quantity: { type: Number, required: true },
    verdict: { type: Object, required: true },
    guests: { type: Number, required: true },
    nights: { type: Number, required: true },
    index: { type: Number, default: 0 },
});

const emit = defineEmits(['update:quantity', 'add']);

const soldOut = computed(() => (props.roomType.available_count ?? 0) === 0);

// Cover first, then the gallery - the cover is the picture the manager chose to
// represent the room.
const images = computed(() => {
    const gallery = props.roomType.gallery ?? [];
    return props.roomType.image_url
        ? [{ id: 'cover', url: props.roomType.image_url }, ...gallery]
        : gallery;
});

const total = computed(() => props.quantity * props.nights * Number(props.roomType.price_per_night));
</script>

<template>
    <article class="overflow-hidden rounded-xl border bg-surface">
        <div class="grid gap-4 p-4 sm:grid-cols-[13rem_minmax(0,1fr)] sm:gap-5">
            <div class="h-40 sm:h-full sm:min-h-[10rem]">
                <ImageGallery
                    :images="images"
                    :alt="roomType.name"
                    variant="compact"
                    icon="bed"
                    :index="index"
                />
            </div>

            <div class="flex min-w-0 flex-col gap-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-semibold text-foreground">{{ roomType.name }}</h3>
                        <TBadge variant="neutral" size="sm">Up to {{ roomType.max_guests }} guests</TBadge>
                        <TBadge v-if="soldOut" variant="neutral" size="sm">Fully booked</TBadge>
                        <TBadge
                            v-else
                            :variant="roomType.available_count > 2 ? 'success' : 'warning'"
                            size="sm"
                            dot
                        >
                            {{ roomType.available_count }} left
                        </TBadge>
                    </div>

                    <p v-if="roomType.description" class="mt-1.5 text-sm leading-relaxed text-foreground-secondary">
                        {{ roomType.description }}
                    </p>
                </div>

                <FacilityList
                    v-if="roomType.amenities?.length"
                    :items="roomType.amenities"
                    variant="chips"
                    :limit="6"
                />

                <!-- Graded in order of severity. Only a quantity the hotel
                     cannot supply disables the button; the rest are guidance
                     for choices that are perfectly legitimate. -->
                <p
                    v-if="!soldOut && verdict.severity"
                    class="text-xs"
                    :class="{
                        'font-medium text-danger': verdict.severity === 'danger',
                        'font-medium text-warning': verdict.severity === 'warning',
                        'text-foreground-muted': verdict.severity === 'info',
                    }"
                >
                    <template v-if="verdict.status === 'exceeds-stock'">
                        Only {{ roomType.available_count }} left for these dates.
                    </template>
                    <template v-else-if="verdict.status === 'over-roomed'">
                        That's {{ verdict.projectedRooms }} rooms for {{ guests }} guest{{ guests === 1 ? '' : 's' }}.
                        Fine if that's deliberate.
                    </template>
                    <template v-else>
                        Sleeps {{ verdict.projectedCapacity }} of {{ guests }}.
                        <template v-if="verdict.cannotFinishAlone">
                            Not enough of this type left for everyone - add another room type too.
                        </template>
                        <template v-else>
                            Add more of these, or mix in another room type.
                        </template>
                    </template>
                </p>

                <div class="mt-auto flex flex-wrap items-end justify-between gap-3 border-t pt-3">
                    <div>
                        <p class="text-lg font-semibold tracking-tight text-foreground">
                            {{ formatMoney(roomType.price_per_night) }}
                            <span class="text-sm font-normal text-foreground-muted">/ night</span>
                        </p>
                        <p v-if="!soldOut" class="text-xs text-foreground-muted">
                            {{ formatMoney(total) }} for {{ nights }} night{{ nights === 1 ? '' : 's' }}
                        </p>
                    </div>

                    <div v-if="soldOut" class="text-sm text-foreground-muted">
                        No rooms of this type left for these dates.
                    </div>
                    <div v-else class="flex items-center gap-3">
                        <TNumberInput
                            label="Rooms"
                            label-position="left"
                            :model-value="quantity"
                            :min="1"
                            :max="roomType.available_count"
                            size="sm"
                            @update:model-value="(value) => emit('update:quantity', value)"
                        />
                        <TButton :disabled="!verdict.canBook" @click="emit('add')">
                            Add to itinerary
                        </TButton>
                    </div>
                </div>
            </div>
        </div>
    </article>
</template>
