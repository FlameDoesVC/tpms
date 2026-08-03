import { reactive } from 'vue';

// Module-scope singleton driving one host component mounted in App.vue - the
// same shape useToast uses. Promise-based so call sites read like the
// window.confirm they replace:
//
//   if (!(await confirm({ message: '…' }))) return;
//
// The alternative (a v-model'd dialog per page) would make a page with two
// distinct confirmations carry two sets of refs and two blocks of markup.
const state = reactive({
    open: false,
    options: {},
});

let resolver = null;

const DEFAULTS = {
    title: 'Are you sure?',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    danger: false,
};

function confirm(options = {}) {
    // A second call while one is open would orphan the first promise, leaving
    // its caller awaiting forever - resolve it as cancelled first.
    if (resolver) resolver(false);

    state.options = { ...DEFAULTS, ...options };
    state.open = true;

    return new Promise((resolve) => {
        resolver = resolve;
    });
}

function settle(result) {
    state.open = false;
    const resolve = resolver;
    resolver = null;
    resolve?.(result);
}

export function useConfirm() {
    return confirm;
}

// Consumed only by ConfirmDialogHost.
export function useConfirmState() {
    return { state, settle };
}
