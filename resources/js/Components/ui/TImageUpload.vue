<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import TIcon from '@/Components/ui/TIcon.vue';
import TButton from '@/Components/ui/TButton.vue';

const props = defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    currentUrl: { type: String, default: null },
});

// `file` is the newly picked upload (or null); `removed` tells the parent to
// clear the existing image on save when the user removed it without picking
// a replacement. Both are separate from `currentUrl`, which the parent owns.
const file = defineModel('file', { default: null });
const removed = defineModel('removed', { type: Boolean, default: false });

const inputRef = ref(null);
const localPreview = ref(null);

watch(file, (newFile) => {
    if (localPreview.value) URL.revokeObjectURL(localPreview.value);
    localPreview.value = newFile ? URL.createObjectURL(newFile) : null;
});

onBeforeUnmount(() => {
    if (localPreview.value) URL.revokeObjectURL(localPreview.value);
});

const previewSrc = computed(() => {
    if (localPreview.value) return localPreview.value;
    if (!removed.value && props.currentUrl) return props.currentUrl;
    return null;
});

const pick = () => inputRef.value?.click();

const onChange = (e) => {
    const picked = e.target.files?.[0] ?? null;
    if (picked) {
        file.value = picked;
        removed.value = false;
    }
    e.target.value = '';
};

const clear = () => {
    file.value = null;
    removed.value = true;
};
</script>

<template>
    <div>
        <label v-if="label" class="mb-1.5 block text-sm font-medium text-foreground">{{ label }}</label>

        <div class="flex items-center gap-4">
            <span class="grid h-20 w-28 shrink-0 place-items-center overflow-hidden rounded-lg border border-[rgb(var(--color-border))] bg-surface-hover text-foreground-muted">
                <img v-if="previewSrc" :src="previewSrc" alt="" class="h-full w-full object-cover" />
                <TIcon v-else name="image" :size="22" />
            </span>

            <div class="flex flex-col items-start gap-2">
                <input ref="inputRef" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="hidden" @change="onChange" />
                <TButton type="button" size="sm" variant="secondary" @click="pick">
                    {{ previewSrc ? 'Replace image' : 'Upload image' }}
                </TButton>
                <button
                    v-if="previewSrc"
                    type="button"
                    class="text-xs text-foreground-muted transition-colors hover:text-danger"
                    @click="clear"
                >
                    Remove image
                </button>
            </div>
        </div>

        <p v-if="error" class="mt-1.5 text-sm text-danger">{{ error }}</p>
    </div>
</template>
