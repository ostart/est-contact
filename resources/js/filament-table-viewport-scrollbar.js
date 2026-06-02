/**
 * Mirror horizontal scrollbar fixed to the viewport bottom for every Filament table
 * in the admin panel (resources, pages, widgets, embedded Livewire tables).
 */
(function () {
    'use strict';

    /** All Filament v5 table horizontal scroll containers */
    const SCROLLER_SELECTOR = '.fi-ta-content-ctn';

    const ACTIVE_CLASS = 'fi-ta-content-ctn--viewport-scrollbar-active';

    /** @type {Map<HTMLElement, HTMLElement>} */
    const mirrors = new Map();

    let scheduled = false;

    /** @type {MutationObserver | null} */
    let domObserver = null;

    function needsHorizontalScroll(element) {
        return element.scrollWidth > element.clientWidth + 1;
    }

    function intersectsViewport(element) {
        const rect = element.getBoundingClientRect();

        return rect.top < window.innerHeight && rect.bottom > 0;
    }

    /** Prefer the table that occupies the most visible area when several overlap at the bottom. */
    function visibleArea(element) {
        const rect = element.getBoundingClientRect();
        const visibleTop = Math.max(rect.top, 0);
        const visibleBottom = Math.min(rect.bottom, window.innerHeight);
        const visibleHeight = Math.max(0, visibleBottom - visibleTop);
        const visibleWidth = Math.max(0, Math.min(rect.width, window.innerWidth));

        return visibleHeight * visibleWidth;
    }

    function removeMirror(scroller) {
        const mirror = mirrors.get(scroller);

        if (! mirror) {
            return;
        }

        mirror.remove();
        mirrors.delete(scroller);
        scroller.classList.remove(ACTIVE_CLASS);
    }

    function ensureMirror(scroller) {
        if (mirrors.has(scroller)) {
            return mirrors.get(scroller);
        }

        const mirror = document.createElement('div');
        mirror.className = 'fi-ta-viewport-scrollbar';
        mirror.setAttribute('role', 'presentation');
        mirror.setAttribute('aria-hidden', 'true');

        const inner = document.createElement('div');
        inner.className = 'fi-ta-viewport-scrollbar__inner';
        mirror.appendChild(inner);

        mirror.addEventListener(
            'scroll',
            () => {
                scroller.scrollLeft = mirror.scrollLeft;
            },
            { passive: true },
        );

        scroller.addEventListener(
            'scroll',
            () => {
                if (mirror.scrollLeft !== scroller.scrollLeft) {
                    mirror.scrollLeft = scroller.scrollLeft;
                }
            },
            { passive: true },
        );

        document.body.appendChild(mirror);
        mirrors.set(scroller, mirror);

        return mirror;
    }

    function hideMirror(scroller, mirror) {
        mirror.style.display = 'none';
        scroller.classList.remove(ACTIVE_CLASS);
    }

    function updateMirror(scroller, mirror) {
        const rect = scroller.getBoundingClientRect();
        const hasOverflow = needsHorizontalScroll(scroller);

        if (! intersectsViewport(scroller) || ! hasOverflow) {
            hideMirror(scroller, mirror);

            return;
        }

        const inner = mirror.querySelector('.fi-ta-viewport-scrollbar__inner');
        inner.style.width = `${scroller.scrollWidth}px`;

        const left = Math.max(rect.left, 0);
        const width = Math.min(rect.width, window.innerWidth - left);

        mirror.style.display = 'block';
        mirror.style.left = `${left}px`;
        mirror.style.width = `${width}px`;

        if (mirror.scrollLeft !== scroller.scrollLeft) {
            mirror.scrollLeft = scroller.scrollLeft;
        }

        scroller.classList.add(ACTIVE_CLASS);
    }

    function findScrollers() {
        return Array.from(document.querySelectorAll(SCROLLER_SELECTOR)).filter((element) => {
            return element.closest('.fi-ta') !== null;
        });
    }

    function update() {
        scheduled = false;

        const scrollers = findScrollers();
        const overflowScrollers = scrollers.filter(needsHorizontalScroll);
        const visibleScrollers = overflowScrollers.filter(intersectsViewport);

        let primaryScroller = null;

        if (visibleScrollers.length > 0) {
            primaryScroller = visibleScrollers.reduce((best, current) => {
                return visibleArea(current) > visibleArea(best) ? current : best;
            });
        }

        scrollers.forEach((scroller) => {
            if (! needsHorizontalScroll(scroller)) {
                removeMirror(scroller);

                return;
            }

            const mirror = ensureMirror(scroller);

            if (visibleScrollers.length > 1 && scroller !== primaryScroller) {
                const primaryRect = primaryScroller.getBoundingClientRect();
                const rect = scroller.getBoundingClientRect();
                const sameHorizontalBand =
                    Math.abs(rect.left - primaryRect.left) < 4 &&
                    Math.abs(rect.width - primaryRect.width) < 4;

                if (sameHorizontalBand) {
                    hideMirror(scroller, mirror);

                    return;
                }
            }

            updateMirror(scroller, mirror);
        });

        mirrors.forEach((mirror, scroller) => {
            if (! document.contains(scroller)) {
                removeMirror(scroller);
            }
        });
    }

    function scheduleUpdate() {
        if (scheduled) {
            return;
        }

        scheduled = true;
        requestAnimationFrame(update);
    }

    function bindDomObserver() {
        if (domObserver) {
            return;
        }

        domObserver = new MutationObserver(scheduleUpdate);
        domObserver.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    function bindLivewire() {
        if (! window.Livewire?.hook) {
            return;
        }

        window.Livewire.hook('commit', ({ succeed }) => {
            succeed(() => scheduleUpdate());
        });

        try {
            window.Livewire.hook('morph.updated', () => scheduleUpdate());
        } catch (_) {
            // Some Livewire versions omit this hook.
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            scheduleUpdate();
            bindDomObserver();
        });
    } else {
        scheduleUpdate();
        bindDomObserver();
    }

    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate, { passive: true });
    document.addEventListener('livewire:navigated', scheduleUpdate);
    document.addEventListener('livewire:update', scheduleUpdate);

    bindLivewire();
})();
