import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// K-PROCLAIM-V1-001A — restrained, progressively-enhanced scroll reveal
// for the public Proclaim theme only. Deliberately does NOT touch markup
// at render time: the `pw-reveal` class (which CSS uses only to define
// the *hidden* starting state) is added here, by JS, right before each
// section is observed — so with JS unavailable, no element is ever given
// that class, and every section renders fully visible from the start.
// Native `IntersectionObserver`, no package, no scroll-jank
// (`window.addEventListener('scroll', ...)` is never used).
document.addEventListener('DOMContentLoaded', () => {
    // K-PROCLAIM-V1-001A §28/§29 — scoped to Home only for this
    // milestone; other pages' reveal behavior is a separate decision.
    if (document.body.dataset.page !== 'home' || typeof IntersectionObserver === 'undefined') {
        return;
    }

    const sections = document.querySelectorAll('main > section:not(.pw-hero)');
    if (sections.length === 0) {
        return;
    }

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    sections.forEach((section) => {
        section.classList.add('pw-reveal');
        observer.observe(section);
    });
});
