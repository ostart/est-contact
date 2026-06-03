/**
 * Mirror horizontal scrollbar fixed to the viewport bottom for Filament tables (desktop).
 * Touch devices swipe the table directly — no mirror bar.
 */
(function () {
    'use strict';

    /** All Filament v5 table horizontal scroll containers */
    const SCROLLER_SELECTOR = '.fi-ta-content-ctn';

    const ACTIVE_CLASS = 'fi-ta-content-ctn--viewport-scrollbar-active';
    const PENDING_CLASS = 'fi-ta-content-ctn--viewport-scrollbar-pending';
    const MIRROR_HIDDEN_CLASS = 'fi-ta-viewport-scrollbar--hidden';

    /** @type {Map<HTMLElement, HTMLElement>} */
    const mirrors = new Map();

    /** @type {Map<HTMLElement, object>} */
    const mirrorState = new Map();

    let scheduled = false;

    /** @type {MutationObserver | null} */
    let domObserver = null;

    /** @type {HTMLElement | null} */
    let lockedPrimary = null;

    let primaryLockUntil = 0;

    const PRIMARY_LOCK_MS = 400;
    const FINE_VIEWPORT_MARGIN = 64;
    const MIRROR_HIDE_DELAY_MS = 500;
    const PAGE_SCROLL_SETTLE_MS = 220;
    const LAYOUT_POSITION_EPSILON = 0.5;
    const COARSE_LAYOUT_POSITION_EPSILON = 3;

    const coarsePointerQuery = window.matchMedia('(hover: none) and (pointer: coarse)');

    function usesViewportMirror() {
        return ! coarsePointerQuery.matches;
    }

    let pageScrolling = false;
    let pageScrollEndTimer = null;
    const KEYBOARD_SCROLL_MIN_STEP = 32;
    const KEYBOARD_SCROLL_VIEWPORT_RATIO = 0.06;
    const KEYBOARD_SCROLL_DURATION_MS = 260;

    function needsHorizontalScroll(element) {
        return element.scrollWidth > element.clientWidth + 1;
    }

    function getViewportMetrics() {
        const viewport = window.visualViewport;

        return {
            height: viewport?.height ?? window.innerHeight,
            width: viewport?.width ?? window.innerWidth,
            offsetTop: viewport?.offsetTop ?? 0,
        };
    }

    function rectIsInPlay(rect, height, offsetTop, marginTop, marginBottom) {
        const topBound = offsetTop - marginTop;
        const bottomBound = offsetTop + height + marginBottom;

        return rect.top < bottomBound && rect.bottom > topBound;
    }

    function isScrollerInPlay(element) {
        const { height, offsetTop } = getViewportMetrics();
        const scrollerRect = element.getBoundingClientRect();

        return rectIsInPlay(
            scrollerRect,
            height,
            offsetTop,
            FINE_VIEWPORT_MARGIN,
            FINE_VIEWPORT_MARGIN,
        );
    }

    function intersectsViewport(element) {
        return isScrollerInPlay(element);
    }

    function visibleArea(element) {
        const rect = element.getBoundingClientRect();
        const visibleTop = Math.max(rect.top, 0);
        const visibleBottom = Math.min(rect.bottom, window.innerHeight);
        const visibleHeight = Math.max(0, visibleBottom - visibleTop);
        const visibleWidth = Math.max(0, Math.min(rect.width, window.innerWidth));

        return visibleHeight * visibleWidth;
    }

    function pickPrimaryScroller(visibleScrollers) {
        if (visibleScrollers.length === 0) {
            lockedPrimary = null;

            return null;
        }

        const now = performance.now();

        if (
            lockedPrimary &&
            document.contains(lockedPrimary) &&
            visibleScrollers.includes(lockedPrimary) &&
            now < primaryLockUntil
        ) {
            return lockedPrimary;
        }

        const best = visibleScrollers.reduce((winner, current) => {
            return visibleArea(current) > visibleArea(winner) ? current : winner;
        });

        if (best !== lockedPrimary) {
            lockedPrimary = best;
            primaryLockUntil = now + PRIMARY_LOCK_MS;
        }

        return best;
    }

    function syncScrollerToMirror(scroller, mirror, state) {
        const delta = Math.abs(mirror.scrollLeft - scroller.scrollLeft);

        if (delta < 1) {
            state.trackedScrollLeft = scroller.scrollLeft;

            return;
        }

        state.syncing = true;
        mirror.scrollLeft = scroller.scrollLeft;
        state.trackedScrollLeft = scroller.scrollLeft;
        requestAnimationFrame(() => {
            state.syncing = false;
        });
    }

    function syncMirrorToScroller(scroller, mirror, state) {
        const delta = Math.abs(mirror.scrollLeft - scroller.scrollLeft);

        if (delta < 1) {
            state.trackedScrollLeft = scroller.scrollLeft;

            return;
        }

        state.syncing = true;
        mirror.scrollLeft = scroller.scrollLeft;
        state.trackedScrollLeft = scroller.scrollLeft;
        requestAnimationFrame(() => {
            state.syncing = false;
        });
    }

    function clampScrollLeft(scroller, scrollLeft) {
        const maxScroll = Math.max(0, scroller.scrollWidth - scroller.clientWidth);

        return Math.max(0, Math.min(maxScroll, scrollLeft));
    }

    function setHorizontalScrollImmediate(scroller, mirror, state, scrollLeft) {
        const next = clampScrollLeft(scroller, scrollLeft);

        scroller.scrollLeft = next;
        mirror.scrollLeft = next;
        state.trackedScrollLeft = next;
    }

    function cancelKeyboardAnimation(state) {
        if (state.keyboardAnimationFrame) {
            cancelAnimationFrame(state.keyboardAnimationFrame);
            state.keyboardAnimationFrame = null;
        }
    }

    function animateHorizontalScroll(scroller, mirror, state, targetScrollLeft) {
        const target = clampScrollLeft(scroller, targetScrollLeft);
        const start = scroller.scrollLeft;

        if (Math.abs(target - start) < 0.5) {
            setHorizontalScrollImmediate(scroller, mirror, state, target);
            state.keyboardScrollTarget = null;
            state.syncing = false;

            return;
        }

        cancelKeyboardAnimation(state);
        state.syncing = true;

        const startTime = performance.now();

        const tick = (now) => {
            const progress = Math.min(1, (now - startTime) / KEYBOARD_SCROLL_DURATION_MS);
            const eased = 1 - (1 - progress) ** 3;
            const value = start + (target - start) * eased;

            setHorizontalScrollImmediate(scroller, mirror, state, value);

            if (progress < 1) {
                state.keyboardAnimationFrame = requestAnimationFrame(tick);

                return;
            }

            state.keyboardAnimationFrame = null;
            state.keyboardScrollTarget = null;
            state.syncing = false;
        };

        state.keyboardAnimationFrame = requestAnimationFrame(tick);
    }

    function applyKeyboardScroll(scroller, mirror, state, delta) {
        const base =
            typeof state.keyboardScrollTarget === 'number'
                ? state.keyboardScrollTarget
                : scroller.scrollLeft;
        const target = clampScrollLeft(scroller, base + delta);

        state.keyboardScrollTarget = target;
        animateHorizontalScroll(scroller, mirror, state, target);
    }

    function markScrollerPending(scroller) {
        scroller.classList.add(PENDING_CLASS);
    }

    function clearScrollerPending(scroller) {
        scroller.classList.remove(PENDING_CLASS);
    }

    function shouldHideNativeScrollbar(scroller) {
        return scroller.closest('.fi-ta') !== null && needsHorizontalScroll(scroller);
    }

    function applyNativeScrollbarHidden(scroller) {
        if (! usesViewportMirror()) {
            scroller.classList.remove(ACTIVE_CLASS, PENDING_CLASS);

            return;
        }

        if (! shouldHideNativeScrollbar(scroller)) {
            scroller.classList.remove(ACTIVE_CLASS, PENDING_CLASS);

            return;
        }

        const scrollLeft = scroller.scrollLeft;

        if (! scroller.classList.contains(ACTIVE_CLASS)) {
            clearScrollerPending(scroller);
            scroller.classList.add(ACTIVE_CLASS);
        }

        scroller.scrollLeft = scrollLeft;
    }

    /** Keep DOM classes in sync — Livewire morph strips them while JS state remains. */
    function syncNativeScrollbarHiding(scroller, state) {
        const shouldHide = shouldHideNativeScrollbar(scroller);
        const isActive = scroller.classList.contains(ACTIVE_CLASS);

        if (shouldHide) {
            applyNativeScrollbarHidden(scroller);
            state.nativeScrollbarHidden = true;
            state.trackedScrollLeft = scroller.scrollLeft;

            return;
        }

        if (isActive) {
            scroller.classList.remove(ACTIVE_CLASS);
        }

        clearScrollerPending(scroller);
        state.nativeScrollbarHidden = false;
    }

    function applyNativeScrollbarHiddenToAll() {
        if (! usesViewportMirror()) {
            return;
        }

        findScrollers().forEach((scroller) => {
            applyNativeScrollbarHidden(scroller);

            const state = mirrorState.get(scroller);

            if (state) {
                state.nativeScrollbarHidden = shouldHideNativeScrollbar(scroller);
                state.trackedScrollLeft = scroller.scrollLeft;
            }
        });
    }

    function purgeAllMirrors() {
        Array.from(mirrors.keys()).forEach((scroller) => {
            removeMirror(scroller);
        });
    }

    /** Touch devices: swipe the table horizontally, no fixed mirror bar. */
    function updateMobileOnly() {
        purgeAllMirrors();

        findScrollers().forEach((scroller) => {
            scroller.classList.remove(ACTIVE_CLASS, PENDING_CLASS);
            delete scroller.dataset.viewportScrollbarObserved;
        });

        cleanupOrphanScrollerClasses();
        lockedPrimary = null;
    }

    function primeMirrorVisibility(scroller, mirror, state, callback) {
        if (state.primed) {
            syncNativeScrollbarHiding(scroller, state);
            callback();

            return;
        }

        if (state.priming) {
            return;
        }

        state.priming = true;

        applyNativeScrollbarHidden(scroller);

        const inner = mirror.querySelector('.fi-ta-viewport-scrollbar__inner');
        const rect = scroller.getBoundingClientRect();
        const left = Math.max(rect.left, 0);
        const width = Math.min(rect.width, window.innerWidth - left);

        setInnerWidth(inner, mirror, scroller.scrollWidth);
        applyLayout(mirror, state, { hidden: true, left, width });
        mirror.scrollLeft = scroller.scrollLeft;
        state.trackedScrollLeft = scroller.scrollLeft;

        requestAnimationFrame(() => {
            state.primed = true;
            syncNativeScrollbarHiding(scroller, state);
            mirror.scrollLeft = scroller.scrollLeft;
            state.trackedScrollLeft = scroller.scrollLeft;

            requestAnimationFrame(() => {
                state.priming = false;
                syncNativeScrollbarHiding(scroller, state);
                callback();
            });
        });
    }

    function getState(scroller, mirror) {
        let state = mirrorState.get(scroller);

        if (! state) {
            state = {
                syncing: false,
                userInteracting: false,
                layout: {},
                trackedScrollLeft: 0,
                nativeScrollbarHidden: false,
                primed: false,
                priming: false,
                keyboardAnimationFrame: null,
                keyboardScrollTarget: null,
                mirrorInPlay: false,
                hideMirrorAfter: 0,
            };
            mirrorState.set(scroller, state);

            const endInteraction = () => {
                state.userInteracting = false;
                cancelKeyboardAnimation(state);
                state.keyboardScrollTarget = null;
                syncMirrorToScroller(scroller, mirror, state);
                scheduleUpdate();
            };

            mirror.addEventListener('pointerdown', () => {
                cancelKeyboardAnimation(state);
                state.keyboardScrollTarget = null;
                state.userInteracting = true;
            });

            mirror.addEventListener('pointerup', endInteraction);
            mirror.addEventListener('pointercancel', endInteraction);
            mirror.addEventListener('lostpointercapture', endInteraction);

            mirror.addEventListener(
                'scroll',
                () => {
                    if (state.syncing) {
                        return;
                    }

                    state.syncing = true;
                    scroller.scrollLeft = mirror.scrollLeft;
                    state.trackedScrollLeft = mirror.scrollLeft;
                    requestAnimationFrame(() => {
                        state.syncing = false;
                    });
                },
                { passive: true },
            );

            scroller.addEventListener(
                'scroll',
                () => {
                    if (state.syncing || state.userInteracting) {
                        return;
                    }

                    syncScrollerToMirror(scroller, mirror, state);
                },
                { passive: true },
            );
        }

        return state;
    }

    function removeMirror(scroller) {
        const mirror = mirrors.get(scroller);

        if (! mirror) {
            return;
        }

        const state = mirrorState.get(scroller);

        if (state) {
            cancelKeyboardAnimation(state);
            state.primed = false;
            state.nativeScrollbarHidden = false;
        }

        scroller.classList.remove(ACTIVE_CLASS, PENDING_CLASS);
        delete scroller.dataset.viewportScrollbarObserved;
        mirror.remove();
        mirrors.delete(scroller);
        mirrorState.delete(scroller);

        if (lockedPrimary === scroller) {
            lockedPrimary = null;
        }
    }

    function layoutEpsilon() {
        return LAYOUT_POSITION_EPSILON;
    }

    function applyLayout(mirror, state, layout) {
        const previous = state.layout;
        const epsilon = layoutEpsilon();

        if (Math.abs(layout.left - (previous.left ?? -1)) > epsilon) {
            mirror.style.left = `${layout.left}px`;
        }

        if (Math.abs(layout.width - (previous.width ?? -1)) > epsilon) {
            mirror.style.width = `${layout.width}px`;
        }

        if (layout.hidden) {
            mirror.classList.add(MIRROR_HIDDEN_CLASS);
        } else {
            mirror.classList.remove(MIRROR_HIDDEN_CLASS);
        }

        state.layout = layout;
    }

    function setInnerWidth(inner, mirror, scrollWidth) {
        const nextWidth = `${scrollWidth}px`;

        if (inner.style.width === nextWidth) {
            return;
        }

        const scrollLeft = mirror.scrollLeft;
        inner.style.width = nextWidth;
        mirror.scrollLeft = scrollLeft;
    }

    function ensureMirror(scroller) {
        if (! usesViewportMirror()) {
            return null;
        }

        if (mirrors.has(scroller)) {
            return mirrors.get(scroller);
        }

        const mirror = document.createElement('div');
        mirror.className = 'fi-ta-viewport-scrollbar fi-ta-viewport-scrollbar--hidden';
        mirror.setAttribute('role', 'presentation');
        mirror.setAttribute('aria-hidden', 'true');

        const inner = document.createElement('div');
        inner.className = 'fi-ta-viewport-scrollbar__inner';
        mirror.appendChild(inner);

        document.body.appendChild(mirror);
        mirrors.set(scroller, mirror);
        applyNativeScrollbarHidden(scroller);
        getState(scroller, mirror);

        return mirror;
    }

    function hideMirror(scroller, mirror, state) {
        applyLayout(mirror, state, {
            hidden: true,
            left: state.layout.left ?? 0,
            width: state.layout.width ?? 0,
        });
        state.priming = false;
        state.mirrorInPlay = false;
        state.hideMirrorAfter = 0;
    }

    function layoutMirrorFrozen(scroller, mirror, state) {
        if (state.layout.hidden) {
            applyLayout(mirror, state, {
                hidden: false,
                left: state.layout.left ?? 0,
                width: state.layout.width ?? 0,
            });
        }
    }

    function layoutMirror(scroller, mirror, state, options = {}) {
        const { freezePosition = false } = options;
        const inner = mirror.querySelector('.fi-ta-viewport-scrollbar__inner');

        if (freezePosition) {
            layoutMirrorFrozen(scroller, mirror, state);

            return;
        }

        const rect = scroller.getBoundingClientRect();
        const { width: viewportWidth } = getViewportMetrics();
        const skipPositionWhileScrolling = pageScrolling && ! state.userInteracting;

        if (! state.userInteracting && ! skipPositionWhileScrolling) {
            setInnerWidth(inner, mirror, scroller.scrollWidth);

            const left = Math.max(rect.left, 0);
            const width = Math.min(rect.width, viewportWidth - left);

            applyLayout(mirror, state, {
                hidden: false,
                left,
                width,
            });
        } else if (state.layout.hidden) {
            applyLayout(mirror, state, {
                hidden: false,
                left: state.layout.left ?? 0,
                width: state.layout.width ?? 0,
            });
        } else if (! state.userInteracting && skipPositionWhileScrolling) {
            setInnerWidth(inner, mirror, scroller.scrollWidth);
            layoutMirrorFrozen(scroller, mirror, state);
        }

        if (pageScrolling) {
            return;
        }

        const scrollDrifted =
            typeof state.trackedScrollLeft !== 'number' ||
            Math.abs(scroller.scrollLeft - state.trackedScrollLeft) >= 1;
        const mirrorDrifted = Math.abs(mirror.scrollLeft - scroller.scrollLeft) >= 1;

        if (! state.userInteracting && ! state.syncing && (scrollDrifted || mirrorDrifted)) {
            syncScrollerToMirror(scroller, mirror, state);
        }
    }

    function shouldDisplayMirror(scroller, state) {
        if (! isScrollerInPlay(scroller)) {
            if (! state.mirrorInPlay) {
                return false;
            }

            if (! state.hideMirrorAfter) {
                state.hideMirrorAfter = performance.now() + MIRROR_HIDE_DELAY_MS;
            }

            return performance.now() < state.hideMirrorAfter;
        }

        state.mirrorInPlay = true;
        state.hideMirrorAfter = 0;

        return true;
    }

    function updateMirror(scroller, mirror) {
        const state = getState(scroller, mirror);
        const hasOverflow = needsHorizontalScroll(scroller);

        if (! hasOverflow) {
            hideMirror(scroller, mirror, state);

            return;
        }

        if (! shouldDisplayMirror(scroller, state)) {
            hideMirror(scroller, mirror, state);

            return;
        }

        const freezePosition =
            pageScrolling &&
            state.hideMirrorAfter > 0 &&
            performance.now() < state.hideMirrorAfter;

        if (! state.primed) {
            primeMirrorVisibility(scroller, mirror, state, () => {
                layoutMirror(scroller, mirror, state, { freezePosition });
            });

            return;
        }

        layoutMirror(scroller, mirror, state, { freezePosition });
        syncNativeScrollbarHiding(scroller, state);
    }

    function getKeyboardScrollStep(scroller) {
        return Math.max(
            KEYBOARD_SCROLL_MIN_STEP,
            Math.round(scroller.clientWidth * KEYBOARD_SCROLL_VIEWPORT_RATIO),
        );
    }

    function isEditableTarget(target) {
        if (! (target instanceof Element)) {
            return false;
        }

        if (target.closest('[contenteditable="true"]')) {
            return true;
        }

        return target.closest(
            'input, textarea, select, option, [role="textbox"], [role="combobox"], [role="searchbox"], .tiptap, .ProseMirror',
        ) !== null;
    }

    function getActiveKeyboardTarget() {
        const scrollers = findScrollers().filter(needsHorizontalScroll).filter(intersectsViewport);

        if (scrollers.length === 0) {
            return null;
        }

        const primary = pickPrimaryScroller(scrollers);
        const scroller = primary ?? scrollers[0];
        const mirror = mirrors.get(scroller);

        if (! mirror || mirror.classList.contains(MIRROR_HIDDEN_CLASS)) {
            return null;
        }

        return {
            scroller,
            mirror,
            state: getState(scroller, mirror),
        };
    }

    function bindKeyboard() {
        document.addEventListener(
            'keydown',
            (event) => {
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                    return;
                }

                if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey) {
                    return;
                }

                if (isEditableTarget(event.target)) {
                    return;
                }

                const target = getActiveKeyboardTarget();

                if (! target) {
                    return;
                }

                const { scroller, mirror, state } = target;
                const step = getKeyboardScrollStep(scroller);
                const delta = event.key === 'ArrowRight' ? step : -step;

                event.preventDefault();
                event.stopPropagation();
                applyKeyboardScroll(scroller, mirror, state, delta);
            },
            true,
        );
    }

    function prewarmScrollers() {
        findScrollers().forEach((scroller) => {
            if (! needsHorizontalScroll(scroller)) {
                return;
            }

            const mirror = ensureMirror(scroller);
            const state = getState(scroller, mirror);

            if (state.primed || state.priming) {
                return;
            }

            const rect = scroller.getBoundingClientRect();
            const left = Math.max(rect.left, 0);
            const width = Math.min(rect.width, window.innerWidth - left);

            primeMirrorVisibility(scroller, mirror, state, () => {
                applyLayout(mirror, state, { hidden: true, left, width });
            });
        });
    }

    let prewarmScheduled = false;

    function schedulePrewarm() {
        if (! usesViewportMirror() || prewarmScheduled) {
            return;
        }

        prewarmScheduled = true;
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                prewarmScheduled = false;
                prewarmScrollers();
            });
        });
    }

    function findScrollers() {
        return Array.from(document.querySelectorAll(SCROLLER_SELECTOR)).filter((element) => {
            return element.closest('.fi-ta') !== null;
        });
    }

    function cleanupOrphanScrollerClasses() {
        document
            .querySelectorAll(`${SCROLLER_SELECTOR}.${ACTIVE_CLASS}, ${SCROLLER_SELECTOR}.${PENDING_CLASS}`)
            .forEach((scroller) => {
                if (! mirrors.has(scroller)) {
                    scroller.classList.remove(ACTIVE_CLASS, PENDING_CLASS);
                }
            });
    }

    function reconcileAllMirrors() {
        mirrors.forEach((mirror, scroller) => {
            if (! document.contains(scroller)) {
                return;
            }

            const state = mirrorState.get(scroller);

            if (! state) {
                return;
            }

            syncNativeScrollbarHiding(scroller, state);
        });
    }

    function resetAfterNavigation() {
        lockedPrimary = null;

        mirrors.forEach((mirror, scroller) => {
            if (! document.contains(scroller)) {
                removeMirror(scroller);

                return;
            }

            const state = mirrorState.get(scroller);

            if (! state) {
                return;
            }

            state.priming = false;
            state.nativeScrollbarHidden = false;
            syncNativeScrollbarHiding(scroller, state);
        });

        cleanupOrphanScrollerClasses();
    }

    /** @type {ResizeObserver | null} */
    let scrollerResizeObserver = null;

    function observeScrollerResize(scroller) {
        if (! scrollerResizeObserver || scroller.dataset.viewportScrollbarObserved === '1') {
            return;
        }

        scroller.dataset.viewportScrollbarObserved = '1';
        scrollerResizeObserver.observe(scroller);
    }

    function update() {
        scheduled = false;

        if (! usesViewportMirror()) {
            updateMobileOnly();

            return;
        }

        applyNativeScrollbarHiddenToAll();

        const scrollers = findScrollers();
        scrollers.forEach(observeScrollerResize);
        const overflowScrollers = scrollers.filter(needsHorizontalScroll);
        const visibleScrollers = overflowScrollers.filter((scroller) => {
            const mirror = mirrors.get(scroller);

            if (! mirror) {
                return isScrollerInPlay(scroller);
            }

            return shouldDisplayMirror(scroller, getState(scroller, mirror));
        });
        const primaryScroller = pickPrimaryScroller(
            visibleScrollers.length > 0 ? visibleScrollers : overflowScrollers.filter(isScrollerInPlay),
        );

        scrollers.forEach((scroller) => {
            if (! needsHorizontalScroll(scroller)) {
                removeMirror(scroller);

                return;
            }

            applyNativeScrollbarHidden(scroller);

            const mirror = ensureMirror(scroller);

            if (! mirror) {
                return;
            }

            if (visibleScrollers.length > 1 && scroller !== primaryScroller) {
                const primaryRect = primaryScroller.getBoundingClientRect();
                const rect = scroller.getBoundingClientRect();
                const sameHorizontalBand =
                    Math.abs(rect.left - primaryRect.left) < 4 &&
                    Math.abs(rect.width - primaryRect.width) < 4;

                if (sameHorizontalBand) {
                    hideMirror(scroller, mirror, getState(scroller, mirror));

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

        cleanupOrphanScrollerClasses();
        reconcileAllMirrors();
    }

    function scheduleUpdate() {
        if (scheduled) {
            return;
        }

        scheduled = true;
        requestAnimationFrame(update);
    }

    function markPageScrolling() {
        if (! usesViewportMirror()) {
            return;
        }

        pageScrolling = true;
        clearTimeout(pageScrollEndTimer);
        pageScrollEndTimer = setTimeout(() => {
            pageScrolling = false;
            scheduleUpdate();
        }, PAGE_SCROLL_SETTLE_MS);
        scheduleUpdate();
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

    function bindResizeObservers() {
        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        scrollerResizeObserver = new ResizeObserver(() => {
            scheduleUpdate();
        });
    }

    function bindLivewire() {
        if (! window.Livewire?.hook) {
            return;
        }

        window.Livewire.hook('commit', ({ succeed }) => {
            applyNativeScrollbarHiddenToAll();

            succeed(() => {
                applyNativeScrollbarHiddenToAll();
                scheduleUpdate();
                schedulePrewarm();
            });
        });

        const morphHooks = ['morph.updating', 'morph.added'];

        morphHooks.forEach((hookName) => {
            try {
                window.Livewire.hook(hookName, () => {
                    applyNativeScrollbarHiddenToAll();
                });
            } catch (_) {
                // Some Livewire versions omit this hook.
            }
        });

        try {
            window.Livewire.hook('morph.updated', () => {
                reconcileAllMirrors();
                scheduleUpdate();
                schedulePrewarm();
            });
        } catch (_) {
            // Some Livewire versions omit this hook.
        }
    }

    function bindTableInteractionGuards() {
        document.addEventListener(
            'click',
            (event) => {
                if (! usesViewportMirror() || ! event.target.closest('.fi-ta')) {
                    return;
                }

                applyNativeScrollbarHiddenToAll();
                scheduleUpdate();
            },
            true,
        );
    }

    function onNavigation() {
        resetAfterNavigation();
        applyNativeScrollbarHiddenToAll();
        scheduleUpdate();
        schedulePrewarm();
    }

    function init() {
        bindDomObserver();
        bindResizeObservers();
        bindKeyboard();
        bindTableInteractionGuards();

        if (usesViewportMirror()) {
            applyNativeScrollbarHiddenToAll();
        } else {
            updateMobileOnly();
        }

        scheduleUpdate();
        schedulePrewarm();

        coarsePointerQuery.addEventListener('change', scheduleUpdate);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.addEventListener('scroll', markPageScrolling, { passive: true });
    window.addEventListener('resize', scheduleUpdate, { passive: true });
    window.addEventListener('orientationchange', scheduleUpdate, { passive: true });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', markPageScrolling, { passive: true });
        window.visualViewport.addEventListener('scroll', markPageScrolling, { passive: true });
    }

    if ('onscrollend' in window) {
        window.addEventListener('scrollend', () => {
            pageScrolling = false;
            clearTimeout(pageScrollEndTimer);
            scheduleUpdate();
        }, { passive: true });
    }

    document.addEventListener('livewire:navigated', onNavigation);

    document.addEventListener('livewire:navigate', () => {
        lockedPrimary = null;
    });

    document.addEventListener('livewire:update', () => {
        applyNativeScrollbarHiddenToAll();
        reconcileAllMirrors();
        scheduleUpdate();
        schedulePrewarm();
    });

    bindLivewire();
})();
