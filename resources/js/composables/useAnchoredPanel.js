import { nextTick, onMounted, onUnmounted, ref, toValue, watch } from 'vue';

/**
 * Positions a popover panel that has been teleported to <body> against the
 * trigger it belongs to.
 *
 * An `absolute` panel inside a component is at the mercy of its ancestors: a
 * TModal dialog clips it with `overflow-hidden`, a TCard does the same, and a
 * scroll container turns it into a second scrollbar. Teleporting the panel out
 * escapes all of that - the cost is that it no longer inherits the trigger's
 * position, so we track the trigger's box by hand and re-run on scroll and
 * resize.
 *
 * `open` is the panel's own visibility ref; the panel must be rendered with
 * `position: fixed` and given the returned `panelStyle`.
 */
export function useAnchoredPanel({
    open,
    // Element the panel hangs off - must contain the trigger, so that a
    // mousedown on the trigger isn't treated as a click outside.
    anchorRef,
    panelRef,
    // 'left' lines the panel's left edge up with the trigger's, 'right' its
    // right edge - for menus that sit at the end of a row.
    align = 'left',
    // 'anchor' matches the trigger's width (what a select wants); a number is
    // a fixed pixel width (what a menu wants). These three accept refs or
    // getters too, for panels whose size is driven by a prop.
    width = 'anchor',
    minWidth = 0,
    gap = 4,
    // Used for the first positioning pass, before the panel exists to measure.
    estimatedHeight = 300,
    onClose = () => {},
}) {
    const VIEWPORT_MARGIN = 8;
    const panelStyle = ref({});

    const resolveWidth = (rect) => {
        const requested = toValue(width);
        const base = requested === 'anchor' ? rect.width : requested;
        return Math.max(base, toValue(minWidth));
    };

    const updatePosition = () => {
        const anchor = anchorRef.value;
        if (!anchor) return;

        const rect = anchor.getBoundingClientRect();
        const w = resolveWidth(rect);
        const height = panelRef.value?.offsetHeight || estimatedHeight;

        // Flip above the trigger only when there is genuinely more room up
        // there; a panel that opens upwards into a tighter gap is worse than
        // one that opens down and is a little short.
        const spaceBelow = window.innerHeight - rect.bottom - VIEWPORT_MARGIN;
        const flipUp = spaceBelow < height && rect.top - VIEWPORT_MARGIN > spaceBelow;

        const left = toValue(align) === 'right' ? rect.right - w : rect.left;
        const maxLeft = Math.max(VIEWPORT_MARGIN, window.innerWidth - w - VIEWPORT_MARGIN);

        panelStyle.value = {
            top: `${flipUp ? Math.max(VIEWPORT_MARGIN, rect.top - height - gap) : rect.bottom + gap}px`,
            left: `${Math.min(Math.max(VIEWPORT_MARGIN, left), maxLeft)}px`,
            width: `${w}px`,
        };
    };

    // Two passes: one so the panel is never painted at the top-left corner,
    // then one after it has rendered and can be measured for the flip check.
    watch(open, (val) => {
        if (!val) return;
        updatePosition();
        nextTick(updatePosition);
    });

    const onViewportChange = () => {
        if (open.value) updatePosition();
    };

    // The panel is outside the anchor in the DOM now, so clicks inside it would
    // otherwise read as clicks outside and close it.
    const onPointerDown = (e) => {
        if (!open.value) return;
        if (anchorRef.value?.contains(e.target)) return;
        if (panelRef.value?.contains(e.target)) return;
        onClose();
    };

    onMounted(() => {
        document.addEventListener('mousedown', onPointerDown);
        // Capture phase, so scrolling a modal body or any inner scroller
        // re-anchors the panel and not just scrolling the window.
        document.addEventListener('scroll', onViewportChange, true);
        window.addEventListener('resize', onViewportChange);
    });

    onUnmounted(() => {
        document.removeEventListener('mousedown', onPointerDown);
        document.removeEventListener('scroll', onViewportChange, true);
        window.removeEventListener('resize', onViewportChange);
    });

    return { panelStyle, updatePosition };
}
