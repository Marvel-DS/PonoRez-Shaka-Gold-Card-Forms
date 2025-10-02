/**
 * main.js
 * Central entry point to initialize all form-related scripts.
 */

import { initGuestTypes } from './guest-types-info.js';
import { initBookingForm } from './booking.js';
import { initCalendar } from './calendar.js';

function initGallery(galleryEl) {
  const slides = Array.from(galleryEl.querySelectorAll('[data-slide]'));
  const prevBtn = galleryEl.querySelector('[data-gallery-prev]');
  const nextBtn = galleryEl.querySelector('[data-gallery-next]');
  const dots = Array.from(galleryEl.querySelectorAll('[data-dot]'));
  let current = 0;

  function showSlide(index) {
    slides.forEach((slide, i) => {
      slide.classList.add('transition-opacity', 'duration-300');
      if (i === index) {
        slide.classList.remove('opacity-0');
        slide.setAttribute('aria-hidden', 'false');
      } else {
        slide.classList.add('opacity-0');
        slide.setAttribute('aria-hidden', 'true');
      }
    });

    dots.forEach((dot, i) => {
      dot.classList.toggle('bg-white/70', i === index);
      dot.classList.toggle('bg-white/30', i !== index);
      dot.setAttribute('aria-selected', i === index ? 'true' : 'false');
    });

    current = index;
  }

  prevBtn?.addEventListener('click', () => {
    current = (current - 1 + slides.length) % slides.length;
    showSlide(current);
  });

  nextBtn?.addEventListener('click', () => {
    current = (current + 1) % slides.length;
    showSlide(current);
  });

  dots.forEach((dot, i) => {
    dot.addEventListener('click', () => {
      current = i;
      showSlide(current);
    });
  });

  // Swipe support for mobile
  const swipeEl = galleryEl.querySelector('[data-gallery-swipe]');
  if (swipeEl) {
    let startX = 0;
    swipeEl.addEventListener('touchstart', e => {
      startX = e.touches[0].clientX;
    });
    swipeEl.addEventListener('touchend', e => {
      const diff = e.changedTouches[0].clientX - startX;
      if (diff > 50) {
        current = (current - 1 + slides.length) % slides.length;
        showSlide(current);
      } else if (diff < -50) {
        current = (current + 1) % slides.length;
        showSlide(current);
      }
    });
  }

  showSlide(0);
}

document.addEventListener("DOMContentLoaded", () => {
  console.log("PonoRez SGC Forms JS initialized");

  try {
    // Initialize guest types info
    initGuestTypes();

    // Initialize all galleries on the page
    document.querySelectorAll('[data-gallery]').forEach(gallery => {
      initGallery(gallery);
    });

    // Initialize all booking forms on the page
    document.querySelectorAll(".BookingForm").forEach(form => {
      //initBookingForm(form);
    });

    // Initialize all calendars on the page
    document.querySelectorAll('[data-calendar]').forEach(calendar => {
      initCalendar(calendar);
    });

  } catch (err) {
    console.error("Init error:", err);
  }
});