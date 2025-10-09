import { qs, qsa } from '../utility/dom.js';

function setActiveSlide(slides, dots, index) {
    slides.forEach((slide, position) => {
        const isActive = position === index;
        slide.classList.toggle('opacity-100', isActive);
        slide.classList.toggle('opacity-0', !isActive);
        slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
    });

    dots.forEach((dot, position) => {
        const isActive = position === index;
        dot.setAttribute('aria-current', isActive ? 'true' : 'false');
        dot.classList.toggle('bg-[var(--sgc-brand-primary)]', isActive);
        dot.classList.toggle('w-4', isActive);
        dot.classList.toggle('bg-white/50', !isActive);
        dot.classList.toggle('w-2', !isActive);
    });
}

function initRoot(root) {
    const container = qs('[data-gallery-root]', root);
    if (!container) {
        return;
    }

    const slides = qsa('[data-gallery-slide]', container);
    if (slides.length <= 1) {
        return;
    }

    const dots = qsa('[data-gallery-dot]', container);
    const prev = qs('[data-gallery-prev]', container);
    const next = qs('[data-gallery-next]', container);

    let current = 0;
    const autoplayDelay = 5000;
    let autoplayTimer = null;

    function stopAutoplay() {
        if (autoplayTimer !== null) {
            window.clearInterval(autoplayTimer);
            autoplayTimer = null;
        }
    }

    function startAutoplay() {
        stopAutoplay();
        autoplayTimer = window.setInterval(() => goToSlide(current + 1, false), autoplayDelay);
    }

    function goToSlide(target, restartTimer = true) {
        const total = slides.length;
        let nextIndex = target;
        if (nextIndex < 0) {
            nextIndex = total - 1;
        }
        if (nextIndex >= total) {
            nextIndex = 0;
        }
        current = nextIndex;
        setActiveSlide(slides, dots, current);
        if (restartTimer) {
            startAutoplay();
        }
    }

    if (prev) {
        prev.addEventListener('click', () => goToSlide(current - 1));
    }

    if (next) {
        next.addEventListener('click', () => goToSlide(current + 1));
    }

    dots.forEach((dot) => {
        const index = Number(dot.dataset.galleryDot);
        if (!Number.isFinite(index)) {
            return;
        }
        dot.addEventListener('click', () => goToSlide(index));
    });

    container.addEventListener('mouseenter', stopAutoplay);
    container.addEventListener('mouseleave', startAutoplay);
    container.addEventListener('focusin', stopAutoplay);
    container.addEventListener('focusout', startAutoplay);

    startAutoplay();
}

export function initGallery() {
    const roots = qsa('[data-component="hero-gallery"]');
    roots.forEach((root) => initRoot(root));
}

export default initGallery;
