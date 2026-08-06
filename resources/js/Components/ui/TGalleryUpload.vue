<script setup>
/**
 * The multi-image counterpart to TImageUpload, for the 'gallery' collection.
 *
 * Deferred in the same way: picking and removing only changes the two models,
 * and the parent performs the uploads and deletes when the form is saved. That
 * keeps a half-filled modal from leaving orphan images behind if it is
 * cancelled.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import TButton from '@/Components/ui/TButton.vue';
import TIcon from '@/Components/ui/TIcon.vue';

const props = defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    // Already uploaded, as [{ id, url }].
    currentImages: { type: Array, default: () => [] },
    max: { type: Number, default: 6 },
});

// Newly picked File objects, and the ids of existing images the user removed.
const files = defineModel('files', { type: Array, default: () => [] });
const removedIds = defineModel('removedIds', { type: Array, default: () => [] });

const inputRef = ref(null);
const previews = ref([]);

watch(
    files,
    (list) => {
        previews.value.forEach((url) => URL.revokeObjectURL(url));
        previews.value = list.map((file) => URL.createObjectURL(file));
    },
    { deep: true }
);

onBeforeUnmount(() => previews.value.forEach((url) => URL.revokeObjectURL(url)));

const keptImages = computed(() =>
    props.currentImages.filter((image) => !removedIds.value.includes(image.id))
);

// The endpoint takes at most `max` files per request, so the picker stops there
// rather than letting the save fail.
const remaining = computed(() => Math.max(0, props.max - files.value.length));

const pick = () => inputRef.value?.click();

const onChange = (event) => {
    const picked = Array.from(event.target.files ?? []).slice(0, remaining.value);
    if (picked.length) files.value = [...files.value, ...picked];
    event.target.value = '';
};

const removeExisting = (id) => {
    removedIds.value = [...removedIds.value, id];
};

const removePicked = (index) => {
    files.value = files.value.filter((_, i) => i !== index);
};
</script>

<template>
    <div>
        <label v-if="label" class="mb-1.5 block text-sm font-medium text-foreground">{{ label }}</label>

        <div v-if="keptImages.length || files.length" class="mb-3 flex flex-wrap gap-2">
            <span
                v-for="image in keptImages"
                :key="`saved-${image.id}`"
                class="group relative block h-16 w-24 overflow-hidden rounded-lg border border-[rgb(var(--color-border))]"
            >
                <img :src="image.url" alt="" class="h-full w-full object-cover" />
                <button
                    type="button"
                    class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded bg-black/60 text-white opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100"
                    aria-label="Remove image"
                    @click="removeExisting(image.id)"
                >
                    <TIcon name="x" :size="12" />
                </button>
            </span>

            <span
                v-for="(preview, index) in previews"
                :key="`new-${index}`"
                class="group relative block h-16 w-24 overflow-hidden rounded-lg border border-dashed border-primary/50"
            >
                <img :src="preview" alt="" class="h-full w-full object-cover" />
                <button
                    type="button"
                    class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded bg-black/60 text-white opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100"
                    aria-label="Remove pending image"
                    @click="removePicked(index)"
                >
                    <TIcon name="x" :size="12" />
                </button>
            </span>
        </div>

        <input
            ref="inputRef"
            type="file"
            multiple
            accept="image/png,image/jpeg,image/webp,image/gif"
            class="hidden"
            @change="onChange"
        />
        <TButton type="button" size="sm" variant="secondary" :disabled="remaining === 0" @click="pick">
            Add photos
        </TButton>
        <p class="mt-1.5 text-xs text-foreground-muted">
            {{ remaining }} more can be uploaded at a time. Saved photos appear on the detail page.
        </p>

        <p v-if="error" class="mt-1.5 text-sm text-danger">{{ error }}</p>
    </div>
</template>
