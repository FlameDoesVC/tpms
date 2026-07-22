import { reactive } from 'vue';

let nextId = 1;
const toasts = reactive([]);

export function showToast(message, type = 'error') {
    const id = nextId++;
    toasts.push({ id, message, type });
    setTimeout(() => removeToast(id), 4000);
}

export function removeToast(id) {
    const index = toasts.findIndex((t) => t.id === id);
    if (index !== -1) toasts.splice(index, 1);
}

export function useToast() {
    return { toasts, showToast, removeToast };
}
