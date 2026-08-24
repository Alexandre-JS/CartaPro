import * as bootstrap from 'bootstrap';

const loader = document.querySelector('[data-pv-loader]');
const finishLoading = () => {
    if (!loader || loader.classList.contains('is-hidden')) return;
    loader.classList.add('is-hidden');
    window.setTimeout(() => loader.remove(), 350);
};

if (document.readyState === 'complete') {
    finishLoading();
} else {
    window.addEventListener('load', finishLoading, { once: true });
    // Nunca bloquear a página por causa de um recurso externo lento.
    window.setTimeout(finishLoading, 2500);
}

const header = document.querySelector('[data-pv-header]');
const updateHeader = () => header?.classList.toggle('is-scrolled', window.scrollY > 12);

updateHeader();
window.addEventListener('scroll', updateHeader, { passive: true });

document.querySelectorAll('#pvMenu a').forEach((link) => {
    link.addEventListener('click', () => {
        const menu = document.getElementById('pvMenu');
        const instance = menu ? bootstrap.Offcanvas.getInstance(menu) : null;
        instance?.hide();
    });
});

const revealItems = document.querySelectorAll('.pv-reveal');
if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    revealItems.forEach((item) => observer.observe(item));
} else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
}
