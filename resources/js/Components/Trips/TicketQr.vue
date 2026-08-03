<script setup>
import { ref } from 'vue';
import QRCode from 'qrcode';
import TIcon from '@/Components/ui/TIcon.vue';
import { showToast } from '@/composables/useToast';

const props = defineProps({
    code: { type: String, required: true },
    size: { type: String, default: 'md' },
    downloadable: { type: Boolean, default: false },
    showCode: { type: Boolean, default: true },
});

const dataUrl = ref('');
const revealed = ref(false);
const busy = ref(false);
const downloading = ref(false);

const SIZES = {
    sm: { box: 'h-14 w-14', glyph: 20, label: 'text-[10px]' },
    md: { box: 'h-20 w-20', glyph: 28, label: 'text-xs' },
};
const dims = SIZES[props.size] ?? SIZES.md;

// Generated on demand rather than upfront for every row - most tickets are
// never actually scanned, and rendering dozens of canvases on mount is wasted
// work on a long list.
const reveal = async () => {
    if (busy.value) return;
    busy.value = true;
    try {
        if (!dataUrl.value) dataUrl.value = await QRCode.toDataURL(props.code);
        revealed.value = true;
    } catch {
        showToast('Could not generate the QR code.', 'error');
    } finally {
        busy.value = false;
    }
};

const hide = () => { revealed.value = false; };

// A separate composition from the on-screen QR: the reference code is drawn
// onto the canvas so it survives being saved as a standalone PNG, which a
// sibling <p> in the DOM would not.
const buildDownloadableQr = async (data) => {
    const qrCanvas = document.createElement('canvas');
    await QRCode.toCanvas(qrCanvas, data, { margin: 1 });

    const fontSize = 16;
    const padding = 10;
    const canvas = document.createElement('canvas');
    canvas.width = qrCanvas.width;
    canvas.height = qrCanvas.height + fontSize + padding * 2;

    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(qrCanvas, 0, 0);
    ctx.fillStyle = '#000000';
    ctx.font = `${fontSize}px monospace`;
    ctx.textAlign = 'center';
    ctx.fillText(data, canvas.width / 2, qrCanvas.height + fontSize + padding / 2);

    return canvas.toDataURL('image/png');
};

const download = async () => {
    if (downloading.value) return;
    downloading.value = true;
    try {
        const link = document.createElement('a');
        link.href = await buildDownloadableQr(props.code);
        link.download = `${props.code}.png`;
        // Appended before clicking - a detached anchor is ignored by some
        // mobile browsers, which is exactly where saving a ticket matters.
        document.body.appendChild(link);
        link.click();
        link.remove();
    } catch {
        showToast('Could not prepare the ticket image.', 'error');
    } finally {
        downloading.value = false;
    }
};

defineExpose({ download });
</script>

<template>
    <div class="flex flex-col items-center gap-1">
        <button
            v-if="!revealed"
            type="button"
            class="flex flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-strong bg-surface-hover text-foreground-muted transition-colors hover:border-primary hover:text-primary disabled:opacity-50"
            :class="dims.box"
            :disabled="busy"
            :aria-label="`Show QR code for ${code}`"
            @click="reveal"
        >
            <TIcon name="qr" :size="dims.glyph" />
            <span class="font-medium" :class="dims.label">{{ busy ? '…' : 'Show QR' }}</span>
        </button>

        <button
            v-else
            type="button"
            class="rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            :aria-label="`Hide QR code for ${code}`"
            @click="hide"
        >
            <img :src="dataUrl" :alt="`QR code for ${code}`" :class="dims.box" />
        </button>

        <p v-if="showCode" class="font-mono text-[11px] text-foreground-muted">{{ code }}</p>

        <button
            v-if="downloadable"
            type="button"
            class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline disabled:opacity-50"
            :disabled="downloading"
            @click="download"
        >
            <TIcon name="download" :size="13" />
            {{ downloading ? 'Preparing…' : 'Save' }}
        </button>
    </div>
</template>
