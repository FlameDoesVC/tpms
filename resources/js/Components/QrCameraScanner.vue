<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import QrScanner from 'qr-scanner';

const emit = defineEmits(['decode']);

const videoEl = ref(null);
const cameraError = ref('');
let scanner = null;

const requestCamera = async () => {
    try {
        await scanner.start();
        cameraError.value = '';
    } catch {
        cameraError.value = 'Camera unavailable - allow camera access, or use the ticket ID field below.';
    }
};

onMounted(() => {
    scanner = new QrScanner(videoEl.value, (result) => emit('decode', result.data), {
        highlightScanRegion: true,
        highlightCodeOutline: true,
        preferredCamera: 'environment',
    });

    requestCamera();
});

onBeforeUnmount(() => {
    scanner?.destroy();
});

// Pausing (not stopping) keeps the camera stream open so resuming doesn't
// re-request camera permission or reinitialize the video track - only
// stopping the scan loop, which is instant either way.
defineExpose({
    pause: () => scanner?.pause(),
    resume: () => scanner?.start(),
});
</script>

<template>
    <div>
        <video ref="videoEl" class="aspect-square w-full rounded-lg bg-black object-cover" />
        <p v-if="cameraError" class="mt-2 text-sm text-danger">
            {{ cameraError }}
            <button type="button" @click="requestCamera" class="font-medium text-primary underline hover:text-primary-hover">
                Allow camera access
            </button>
        </p>
    </div>
</template>
