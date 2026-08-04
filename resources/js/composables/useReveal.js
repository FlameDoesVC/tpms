import { nextTick, onMounted, onUnmounted } from 'vue';

/**
 * Reveals elements as they scroll into view.
 *
 * One observer for the whole page, driven off a `data-reveal` attribute, so
 * revealing a section costs an attribute rather than a wrapper component
 * around every block.
 *
 * The hidden state lives behind a `.reveal-on` class that only this file
 * adds, and only once it is certain the observer exists. A stylesheet that
 * hides `[data-reveal]` unconditionally would leave the whole page blank if
 * the bundle failed or the browser had no IntersectionObserver; this way the
 * worst case is content that simply never animates.
 */
export function useReveal(rootRef, { rootMargin = '0px 0px -10% 0px', threshold = 0.1 } = {}) {
    let observer = null;
    // Elements are observed once and released on reveal, so a section can't
    // re-hide itself when the visitor scrolls back up past it.
    const claimed = new WeakSet();

    const scan = async () => {
        await nextTick();
        const root = rootRef?.value;
        if (!root || !observer) return;

        for (const el of root.querySelectorAll('[data-reveal]')) {
            if (claimed.has(el)) continue;
            claimed.add(el);
            observer.observe(el);
        }
    };

    onMounted(() => {
        const root = rootRef?.value;
        if (!root || typeof IntersectionObserver === 'undefined') return;

        observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (!entry.isIntersecting) continue;
                    entry.target.classList.add('is-in');
                    observer.unobserve(entry.target);
                }
            },
            { rootMargin, threshold },
        );

        // Synchronous, before the browser paints this frame - so nothing is
        // ever seen in its final position and then yanked back to hidden.
        root.classList.add('reveal-on');
        scan();
    });

    onUnmounted(() => {
        observer?.disconnect();
        observer = null;
    });

    // Sections that only exist after a fetch resolves have to be picked up
    // when they appear; the initial scan ran before they were in the DOM.
    return { scan };
}
