/**
 * Pretty Reviews — scroll-snap carousel controller.
 *
 * The track itself is a native horizontally-scrollable, snap-aligned flex row,
 * so touch swipe and free scrolling work without JS. This script only adds the
 * prev/next buttons (which advance one column at a time) and optional autoplay.
 */

const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)');

const scrollBehavior = () => (REDUCED_MOTION.matches ? 'auto' : 'smooth');

const enhance = (wrapper) => {
    const track = wrapper.querySelector('[data-prettyreviews-track]');

    if (!track) {
        return;
    }

    const controls = wrapper.querySelector('[data-prettyreviews-carousel-controls]');
    const prevBtn = wrapper.querySelector('[data-prettyreviews-prev]');
    const nextBtn = wrapper.querySelector('[data-prettyreviews-next]');

    // Distance of a single column (slide width + the flex gap).
    const columnStep = () => {
        const slide = track.querySelector('[data-prettyreviews-review]');

        if (!slide) {
            return track.clientWidth;
        }

        const gap = Number.parseFloat(window.getComputedStyle(track).columnGap) || 0;

        return slide.offsetWidth + gap;
    };

    const atStart = () => track.scrollLeft <= 1;
    const atEnd = () => Math.ceil(track.scrollLeft + track.clientWidth) >= track.scrollWidth - 1;
    const scrollable = () => track.scrollWidth > track.clientWidth + 1;

    const refresh = () => {
        if (controls) {
            controls.classList.toggle('d-none', !scrollable());
        }

        if (prevBtn) {
            prevBtn.disabled = atStart();
        }

        if (nextBtn) {
            nextBtn.disabled = atEnd();
        }
    };

    const step = (direction) => {
        track.scrollBy({ left: direction * columnStep(), behavior: scrollBehavior() });
    };

    prevBtn?.addEventListener('click', () => step(-1));
    nextBtn?.addEventListener('click', () => step(1));

    let scrollFrame = null;
    track.addEventListener('scroll', () => {
        window.cancelAnimationFrame(scrollFrame);
        scrollFrame = window.requestAnimationFrame(refresh);
    }, { passive: true });

    if ('ResizeObserver' in window) {
        new ResizeObserver(refresh).observe(track);
    } else {
        window.addEventListener('resize', refresh);
    }

    refresh();

    if (track.dataset.autoplay !== '1' || REDUCED_MOTION.matches) {
        return;
    }

    let timer = null;
    const interval = Number.parseInt(track.dataset.autoplayInterval, 10) || 5000;

    const advance = () => {
        if (atEnd()) {
            track.scrollTo({ left: 0, behavior: scrollBehavior() });
        } else {
            step(1);
        }
    };

    const start = () => {
        if (timer === null && scrollable()) {
            timer = window.setInterval(advance, interval);
        }
    };

    const stop = () => {
        if (timer !== null) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    wrapper.addEventListener('pointerenter', stop);
    wrapper.addEventListener('pointerleave', start);
    wrapper.addEventListener('focusin', stop);
    wrapper.addEventListener('focusout', start);
    document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));

    start();
};

document.querySelectorAll('[data-prettyreviews-carousel-wrapper]').forEach(enhance);
